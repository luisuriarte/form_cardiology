#!/usr/bin/env python3
"""Rebuild lang_custom.sql from the rows of an existing lang_custom.sql.

Accepted row formats (any mix, in INSERT ... VALUES blocks):
  ('constant', 'definition')
  ('Spanish (Latin American)', 'el', 'constant', 'definition')
Values may be plain literals ('' and backslash escapes allowed) or
CONCAT('a', CHAR(59), 'b') built from literals and CHAR(n).
Exact duplicates are dropped (first one wins). Constants with DIFFERENT definitions are reported
and the first one is kept: review them.

Usage: python3 tools/fix_lang_sql.py lang_custom.sql.bak lang_custom.sql
"""
import re
import sys

WS = re.compile(r"(?:\s+|--[^\n]*)+")
CONCAT_RE = re.compile(r"CONCAT\s*\(", re.I)
CHAR_RE = re.compile(r"CHAR\s*\(\s*(\d+)\s*(?:USING\s+\w+\s*)?\)", re.I)
BLOCK = re.compile(
    r"INSERT\s+(?:IGNORE\s+)?INTO\s+(?:tmp_card_lang\s*\(\s*constant_name\s*,\s*definition\s*\)"
    r"|lang_custom\s*\([^)]*\))\s*VALUES", re.I)

TEMPLATE = """-- =============================================================================
-- lang_custom.sql  --  Cardiology form (form_cardiology)
-- GENERATED FILE: do not edit by hand. Source: lang/es_AR.tsv
-- Language: Spanish (Latin American)
-- Safe to run any number of times, on a fresh install or on an upgrade.
-- RULE: an existing non-empty translation is NEVER overwritten.
-- Run it as ONE script in ONE session (user variables and a temporary table are used).
-- =============================================================================
START TRANSACTION;

SET @lang_desc := 'Spanish (Latin American)';
SET @lang_id   := (SELECT lang_id   FROM lang_languages WHERE lang_description = @lang_desc LIMIT 1);
SET @lang_code := (SELECT lang_code FROM lang_languages WHERE lang_description = @lang_desc LIMIT 1);

SELECT IF(@lang_id IS NULL,
          'ERROR: language not found, nothing will be written',
          CONCAT('Language found: ', @lang_code)) AS check_language;

-- Strings of this module go into a temporary table, nothing is written yet.
-- Collations are explicit: lang_constants.constant_name is utf8mb4_bin.
DROP TEMPORARY TABLE IF EXISTS tmp_card_lang;
CREATE TEMPORARY TABLE tmp_card_lang (
  constant_name VARCHAR(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  definition    TEXT         CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  PRIMARY KEY (constant_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

INSERT INTO tmp_card_lang (constant_name, definition) VALUES
@@ROWS@@;

-- A. Add the source constants that do not exist yet
INSERT INTO lang_constants (constant_name)
SELECT t.constant_name
FROM tmp_card_lang t
WHERE @lang_id IS NOT NULL
  AND NOT EXISTS (
    SELECT 1 FROM lang_constants c WHERE c.constant_name = t.constant_name
  );

-- B. Fill definitions that exist but are EMPTY (a non-empty one is never touched)
UPDATE lang_definitions d
INNER JOIN lang_constants c ON c.cons_id = d.cons_id
INNER JOIN tmp_card_lang t  ON c.constant_name = t.constant_name
SET d.definition = t.definition
WHERE d.lang_id = @lang_id
  AND IFNULL(d.definition, '') = '';

-- C. Insert definitions for constants that have NO row for this language
INSERT INTO lang_definitions (cons_id, lang_id, definition)
SELECT c.cons_id, @lang_id, t.definition
FROM tmp_card_lang t
INNER JOIN lang_constants c ON c.constant_name = t.constant_name
WHERE @lang_id IS NOT NULL
  AND NOT EXISTS (
    SELECT 1 FROM lang_definitions d
    WHERE d.cons_id = c.cons_id AND d.lang_id = @lang_id
  );

-- D. Record in lang_custom ONLY what ended up with OUR definition
INSERT INTO lang_custom (lang_description, lang_code, constant_name, definition)
SELECT @lang_desc, @lang_code, t.constant_name, t.definition
FROM tmp_card_lang t
INNER JOIN lang_constants c   ON c.constant_name = t.constant_name
INNER JOIN lang_definitions d ON d.cons_id = c.cons_id AND d.lang_id = @lang_id
                              AND d.definition = t.definition COLLATE utf8mb4_bin
WHERE @lang_id IS NOT NULL
  AND NOT EXISTS (
    SELECT 1 FROM lang_custom lc
    WHERE lc.lang_code = @lang_code
      AND lc.constant_name = t.constant_name COLLATE utf8mb4_bin
  );

-- E. Report
SELECT COUNT(*) AS strings_in_file FROM tmp_card_lang;

SELECT t.constant_name, d.definition AS kept_definition, t.definition AS our_definition
FROM tmp_card_lang t
INNER JOIN lang_constants c   ON c.constant_name = t.constant_name
INNER JOIN lang_definitions d ON d.cons_id = c.cons_id AND d.lang_id = @lang_id
WHERE IFNULL(d.definition, '') <> ''
  AND d.definition <> t.definition COLLATE utf8mb4_bin
ORDER BY t.constant_name;

COMMIT;
DROP TEMPORARY TABLE IF EXISTS tmp_card_lang;
"""


def skip(text, pos):
    m = WS.match(text, pos)
    return m.end() if m else pos


def line_of(text, pos):
    return text.count("\n", 0, pos) + 1


def parse_literal(text, pos):
    """Parse a SQL single-quoted literal starting at pos. Returns (decoded string, new pos)."""
    i, out = pos + 1, []
    escapes = {"n": "\n", "r": "\r", "t": "\t", "0": "\0"}
    while i < len(text):
        ch = text[i]
        if ch == "\\" and i + 1 < len(text):
            out.append(escapes.get(text[i + 1], text[i + 1]))
            i += 2
        elif ch == "'":
            if i + 1 < len(text) and text[i + 1] == "'":
                out.append("'")
                i += 2
            else:
                return "".join(out), i + 1
        else:
            out.append(ch)
            i += 1
    raise ValueError("unterminated string literal")


def parse_arg(text, pos):
    if text[pos] == "'":
        return parse_literal(text, pos)
    m = CHAR_RE.match(text, pos)
    if m:
        return chr(int(m.group(1))), m.end()
    raise ValueError("unsupported expression inside CONCAT")


def parse_value(text, pos):
    if text[pos] == "'":
        return parse_literal(text, pos)
    m = CONCAT_RE.match(text, pos)
    if not m:
        raise ValueError("unsupported value (expected a literal or CONCAT)")
    pos, parts = m.end(), []
    while True:
        pos = skip(text, pos)
        val, pos = parse_arg(text, pos)
        parts.append(val)
        pos = skip(text, pos)
        if text[pos] == ",":
            pos += 1
        elif text[pos] == ")":
            return "".join(parts), pos + 1
        else:
            raise ValueError("expected ',' or ')' inside CONCAT")


def parse_row(text, pos):
    """text[pos] is '('. Returns (list of decoded values, new pos)."""
    pos, vals = skip(text, pos + 1), []
    while True:
        val, pos = parse_value(text, pos)
        vals.append(val)
        pos = skip(text, pos)
        if text[pos] == ",":
            pos = skip(text, pos + 1)
        elif text[pos] == ")":
            return vals, pos + 1
        else:
            raise ValueError("expected ',' or ')' in the row")


def esc(s):
    return (s.replace("\\", "\\\\").replace("'", "''")
             .replace("\n", "\\n").replace("\r", "\\r").replace("\t", "\\t"))


def sql_value(s):
    """Literal for the output. A semicolon is written as CHAR(59 USING utf8mb4) so naive splitters survive."""
    if ";" not in s:
        return "'" + esc(s) + "'"
    parts, items = s.split(";"), []
    for i, p in enumerate(parts):
        if p:
            items.append("'" + esc(p) + "'")
        if i < len(parts) - 1:
            items.append("CHAR(59 USING utf8mb4)")
    return "CONCAT(" + ", ".join(items) + ")"


def main(src, dst):
    text = open(src, encoding="utf-8").read()
    seen, rows, conflicts = {}, [], []
    exact_dups = converted = total = 0

    for block in BLOCK.finditer(text):
        pos = block.end()
        while True:
            pos = skip(text, pos)
            if pos < len(text) and text[pos] == ",":
                pos += 1
                continue
            if pos >= len(text) or text[pos] != "(":
                break
            try:
                vals, newpos = parse_row(text, pos)
            except (ValueError, IndexError) as exc:
                sys.exit("Cannot parse the row at line %d (%s):\n%r"
                         % (line_of(text, pos), exc, text[pos:pos + 300]))
            if len(vals) == 4 and vals[0] == "Spanish (Latin American)" and vals[1] == "el":
                const, definition = vals[2], vals[3]
                converted += 1
            elif len(vals) == 2:
                const, definition = vals
            else:
                sys.exit("Unexpected number of values (%d) at line %d:\n%r"
                         % (len(vals), line_of(text, pos), text[pos:pos + 300]))
            total += 1
            line = line_of(text, pos)
            if const in seen:
                if seen[const][0] == definition:
                    exact_dups += 1
                else:
                    conflicts.append((const, seen[const], (definition, line)))
            else:
                seen[const] = (definition, line)
                rows.append((const, definition))
            pos = newpos

    if not rows:
        sys.exit("No rows found. Nothing written.")

    body = ",\n".join("(%s, %s)" % (sql_value(c), sql_value(d)) for c, d in rows)
    with open(dst, "w", encoding="utf-8", newline="\n") as fh:
        fh.write(TEMPLATE.replace("@@ROWS@@", body))

    print("Rows read: %d | unique: %d | exact duplicates dropped: %d | old-format rows converted: %d | conflicts: %d"
          % (total, len(rows), exact_dups, converted, len(conflicts)))
    for const, first, second in conflicts:
        print("CONFLICT  %s\n   kept    (line %d): %s\n   ignored (line %d): %s"
              % (const, first[1], first[0], second[1], second[0]))


if __name__ == "__main__":
    if len(sys.argv) != 3:
        sys.exit(__doc__)
    main(sys.argv[1], sys.argv[2])