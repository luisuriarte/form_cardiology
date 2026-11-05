<?php

/**
 * CardDocumentCategories - Cardiology document category tree helper
 *
 * Centralises resolution of the Cardiology root category, traversal of its
 * subtree by the `parent` column (never by lft/rght), subtree validation, and
 * rendering of the folder tree used by the upload dialog.
 *
 * Compatible with OpenEMR 8.2.0 - 8.5.0.
 *
 * @package   OpenEMR
 * @subpackage Cardiology
 * @link      https://www.open-emr.org
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\Forms\Cardiology;

final class CardDocumentCategories
{
    /** Preferred root names, in resolution order. */
    public const ROOT_NAMES = ['Cardiology Records', 'Cardiology'];

    /** Legacy root ID used only when no category matches ROOT_NAMES. */
    public const LEGACY_ROOT_ID = 22000;

    /** Maximum number of parent hops accepted while walking a subtree. */
    private const MAX_DEPTH = 32;

    private static bool $rootResolved = false;

    private static int $rootId = 0;

    /**
     * Resolves the Cardiology root category ID by name.
     *
     * Resolution order:
     *   1. "Cardiology Records"
     *   2. "Cardiology"
     *   3. Legacy ID 22000 when that row still exists
     *
     * Returns 0 when no root can be found.
     */
    public static function getRootId(): int
    {
        if (self::$rootResolved) {
            return self::$rootId;
        }

        self::$rootResolved = true;
        self::$rootId       = 0;

        foreach (self::ROOT_NAMES as $name) {
            $row = sqlQuery(
                "SELECT id FROM `categories`
                 WHERE name = ?
                 ORDER BY (aco_spec = 'patients|docs') DESC, id ASC
                 LIMIT 1",
                [$name]
            );
            if ($row && !empty($row['id'])) {
                self::$rootId = (int)$row['id'];
                return self::$rootId;
            }
        }

        $legacy = sqlQuery('SELECT id FROM `categories` WHERE id = ? LIMIT 1', [self::LEGACY_ROOT_ID]);
        if ($legacy && !empty($legacy['id'])) {
            self::$rootId = (int)$legacy['id'];
        }

        return self::$rootId;
    }

    /**
     * Returns the subtree rooted at the Cardiology category as a flat BFS list.
     *
     * Each element: ['id' => int, 'name' => string, 'parent' => int, 'depth' => int]
     *
     * @return list<array{id:int,name:string,parent:int,depth:int}>
     */
    public static function getCategoryTree(): array
    {
        $rootId = self::getRootId();
        if ($rootId <= 0) {
            return [];
        }

        [$all, $children] = self::loadSubtree();
        if (!isset($all[$rootId])) {
            return [];
        }

        $tree    = [];
        $queue   = [[$rootId, 0]];
        $visited = [];

        while (!empty($queue)) {
            [$id, $depth] = array_shift($queue);
            if (isset($visited[$id]) || !isset($all[$id])) {
                continue;
            }
            $visited[$id] = true;

            $node   = $all[$id];
            $tree[] = [
                'id'     => $node['id'],
                'name'   => $node['name'],
                'parent' => $node['parent'],
                'depth'  => $depth,
            ];

            foreach ($children[$id] ?? [] as $childId) {
                $queue[] = [$childId, $depth + 1];
            }
        }

        return $tree;
    }

    /**
     * Returns every selectable category (all subtree nodes except the root).
     *
     * @return list<array{id:int,name:string,parent:int,depth:int}>
     */
    public static function getSelectableCategories(): array
    {
        $rootId = self::getRootId();

        return array_values(array_filter(
            self::getCategoryTree(),
            static fn(array $node): bool => (int)$node['id'] !== $rootId
        ));
    }

    /**
     * Returns the display name of a category, or an empty string when missing.
     */
    public static function getName(int $categoryId): string
    {
        if ($categoryId <= 0) {
            return '';
        }

        $row = sqlQuery('SELECT name FROM `categories` WHERE id = ? LIMIT 1', [$categoryId]);

        return ($row && isset($row['name'])) ? (string)$row['name'] : '';
    }

    /**
     * Returns true when the category is the Cardiology root or one of its descendants.
     */
    public static function isWithinRoot(int $categoryId): bool
    {
        if ($categoryId <= 0) {
            return false;
        }

        $rootId = self::getRootId();
        if ($rootId <= 0) {
            return false;
        }

        $current = $categoryId;
        $seen    = [];

        for ($i = 0; $i < self::MAX_DEPTH; $i++) {
            if ($current === $rootId) {
                return true;
            }
            if (isset($seen[$current])) {
                return false;
            }
            $seen[$current] = true;

            $row = sqlQuery('SELECT parent FROM `categories` WHERE id = ? LIMIT 1', [$current]);
            if (!$row) {
                return false;
            }

            $parent = (int)($row['parent'] ?? 0);
            if ($parent <= 0 || $parent === $current) {
                return false;
            }

            $current = $parent;
        }

        return false;
    }

    /**
     * Returns true when the category may receive uploaded documents.
     *
     * The root folder is a container only; every descendant can be selected.
     */
    public static function isSelectable(int $categoryId): bool
    {
        $rootId = self::getRootId();

        return $rootId > 0
            && $categoryId !== $rootId
            && self::isWithinRoot($categoryId);
    }

    /**
     * Renders the folder tree HTML for the upload dialog.
     *
     * The root node is used only as the traversal origin and is not rendered.
     */
    public static function renderTreeHtml(): string
    {
        $rootId = self::getRootId();
        if ($rootId <= 0) {
            return '<div class="p-3 text-muted small">' . text(xlt('No categories available.')) . '</div>';
        }

        [$all, $children] = self::loadSubtree();
        if (!isset($all[$rootId]) || empty($children[$rootId])) {
            return '<div class="p-3 text-muted small">' . text(xlt('No categories available.')) . '</div>';
        }

        return self::renderNodes($children[$rootId], $all, $children, 0);
    }

    /**
     * Loads every category once and builds an adjacency map by parent.
     *
     * @return array{0: array<int, array{id:int,name:string,parent:int}>, 1: array<int, list<int>>}
     */
    private static function loadSubtree(): array
    {
        $all      = [];
        $children = [];

        $res = sqlStatement('SELECT id, name, parent FROM `categories` ORDER BY id');
        while ($row = sqlFetchArray($res)) {
            $id   = (int)$row['id'];
            $all[$id] = [
                'id'     => $id,
                'name'   => (string)($row['name'] ?? ''),
                'parent' => (int)($row['parent'] ?? 0),
            ];
        }

        foreach ($all as $id => $node) {
            $parent = $node['parent'];
            if ($parent !== $id && isset($all[$parent])) {
                $children[$parent][] = $id;
            }
        }

        return [$all, $children];
    }

    /**
     * Recursively renders tree nodes and their child containers.
     *
     * @param list<int>                                    $ids
     * @param array<int, array{id:int,name:string,parent:int}> $all
     * @param array<int, list<int>>                        $children
     */
    private static function renderNodes(array $ids, array $all, array $children, int $depth): string
    {
        if ($depth > self::MAX_DEPTH) {
            return '';
        }

        $html = '';

        foreach ($ids as $id) {
            if (!isset($all[$id])) {
                continue;
            }

            $node        = $all[$id];
            $hasChildren = !empty($children[$id]);
            $name        = (string)$node['name'];
            $icon        = $hasChildren ? 'fa-folder' : 'fa-folder-open';
            $toggleClass = $hasChildren ? 'collapsed' : 'invisible';
            $nodeClass   = 'cardiology-tree-node' . ($hasChildren ? ' has-children' : ' is-selectable');

            $html .= '<div class="' . $nodeClass . '"'
                . ' data-id="' . (int)$id . '"'
                . ' data-name="' . attr($name) . '"'
                . ' data-selectable="' . ($hasChildren ? '0' : '1') . '"'
                . ' role="treeitem"'
                . ' tabindex="0"'
                . ' aria-expanded="' . ($hasChildren ? 'false' : 'true') . '">'
                . '<span class="cardiology-tree-indent" style="width:' . ($depth * 18) . 'px"></span>'
                . '<span class="cardiology-tree-toggle ' . $toggleClass . '"><i class="fa fa-caret-down" aria-hidden="true"></i></span>'
                . '<span class="cardiology-tree-icon"><i class="fa ' . $icon . '" aria-hidden="true"></i></span>'
                . '<span class="cardiology-tree-label">' . text($name) . '</span>'
                . '</div>';

            if ($hasChildren) {
                $html .= '<div class="cardiology-tree-children is-collapsed"'
                    . ' id="cardiology-tree-children-' . (int)$id . '"'
                    . ' role="group">';
                $html .= self::renderNodes($children[$id], $all, $children, $depth + 1);
                $html .= '</div>';
            }
        }

        return $html;
    }
}
