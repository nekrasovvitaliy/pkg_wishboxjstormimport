<?php
/**
 * @copyright   (c) 2013-2026 Nekrasov Vitaliy <nekrasov_vitaliy@list.ru>
 * @license     GNU General Public License version 2 or later;
 */

namespace WishboxJsToRmImportLibrary\Service;

use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Throwable;

defined('_JEXEC') or die;

/**
 * Looks up RadicalMart records used during catalog import.
 *
 * @since 1.0.0
 */
final readonly class RadicalMartLookupService
{
    /**
     * @param   DatabaseInterface  $db  Database.
     *
     * @since 1.0.0
     */
    public function __construct(private DatabaseInterface $db)
    {
    }

    /**
     * @param   string   $type      RadicalMart category type.
     * @param   string   $alias     Alias.
     * @param   string   $title     Title.
     * @param   integer  $parentId  Parent ID.
     *
     * @return object|null
     *
     * @since 1.0.0
     */
    public function findExistingCategory(string $type, string $alias, string $title, int $parentId): ?object
    {
        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__radicalmart_categories'))
            ->where($this->db->quoteName('type') . ' = :type')
            ->where($this->db->quoteName('parent_id') . ' = :parent_id')
            ->bind(':type', $type)
            ->bind(':parent_id', $parentId, ParameterType::INTEGER)
            ->setLimit(1);

        if ($alias !== '') {
            $query->where($this->db->quoteName('alias') . ' = :alias')
                ->bind(':alias', $alias);
        } else {
            $query->where($this->db->quoteName('title') . ' = :title')
                ->bind(':title', $title);
        }

        return $this->db->setQuery($query)->loadObject() ?: null;
    }

    /**
     * @param   array   $columns  Product table columns.
     * @param   string  $alias    Alias.
     * @param   string  $title    Title.
     *
     * @return object|null
     *
     * @since 1.0.0
     */
    public function findExistingProduct(array $columns, string $alias, string $title): ?object
    {
        if (!isset($columns['alias']) || !isset($columns['title'])) {
            return null;
        }

        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__radicalmart_products'))
            ->setLimit(1);

        if ($alias !== '') {
            $query->where($this->db->quoteName('alias') . ' = :alias')
                ->bind(':alias', $alias);
        } else {
            $query->where($this->db->quoteName('title') . ' = :title')
                ->bind(':title', $title);
        }

        return $this->db->setQuery($query)->loadObject() ?: null;
    }

    /**
     * @param   string  $alias  Alias.
     *
     * @return object|null
     *
     * @since 1.0.0
     */
    public function findExistingField(string $alias): ?object
    {
        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__radicalmart_fields'))
            ->where($this->db->quoteName('area') . ' = ' . $this->db->quote('products'))
            ->where($this->db->quoteName('alias') . ' = :alias')
            ->bind(':alias', $alias)
            ->setLimit(1);

        return $this->db->setQuery($query)->loadObject() ?: null;
    }

    /**
     * @param   string  $type       RadicalMart category type.
     * @param   string  $paramName  Import param name.
     *
     * @return array<string, int>
     *
     * @since 1.0.0
     */
    public function loadImportedCategoryMap(string $type, string $paramName): array
    {
        $paramSearch = '%' . $paramName . '%';
        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName(['id', 'params']))
            ->from($this->db->quoteName('#__radicalmart_categories'))
            ->where($this->db->quoteName('type') . ' = :type')
            ->where($this->db->quoteName('params') . ' LIKE :param')
            ->bind(':type', $type)
            ->bind(':param', $paramSearch);

        $map = [];

        foreach ($this->db->setQuery($query)->loadObjectList() as $row) {
            $params = json_decode((string) $row->params, true);

            if (!is_array($params)) {
                continue;
            }

            $oldId = trim((string) ($params[$paramName] ?? ''));

            if ($oldId !== '') {
                $map[$oldId] = (int) $row->id;
            }
        }

        return $map;
    }

    /**
     * @param   string  $table  Table name.
     *
     * @return array
     *
     * @since 1.0.0
     */
    public function tableColumns(string $table): array
    {
        if (!method_exists($this->db, 'getTableColumns')) {
            return [];
        }

        try {
            $columns = $this->db->getTableColumns($table);
        } catch (Throwable) {
            return [];
        }

        return array_fill_keys(array_keys($columns), true);
    }
}
