<?php

namespace Webkul\Admin\DataGrids\Settings;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Webkul\DataGrid\DataGrid;

class WarehouseDataGrid extends DataGrid
{
    /**
     * Prepare query builder.
     *
     * @return void
     */
    public function prepareQueryBuilder(): Builder
    {
        $queryBuilder = DB::table('warehouses')
            ->leftJoin('product_inventories', 'warehouses.id', '=', 'product_inventories.warehouse_id')
            ->select(
                'warehouses.id',
                'warehouses.name',
                'warehouses.contact_name',
                'warehouses.contact_emails',
                'warehouses.contact_numbers',
                'warehouses.created_at',
            )
            ->addSelect(DB::raw('count(DISTINCT '.DB::getTablePrefix().'product_inventories.product_id) as products'))
            ->groupBy('warehouses.id');

        $this->addFilter('id', 'warehouses.id');
        $this->addFilter('created_at', 'warehouses.created_at');

        return $queryBuilder;
    }

    /**
     * Add columns.
     */
    public function prepareColumns(): void
    {
        $this->addColumn([
            'index' => 'id',
            'label' => trans('admin::app.settings.warehouses.index.datagrid.id'),
            'type' => 'string',
            'sortable' => true,
            'filterable' => true,
        ]);

        $this->addColumn([
            'index' => 'name',
            'label' => trans('admin::app.settings.warehouses.index.datagrid.name'),
            'type' => 'string',
            'sortable' => true,
            'filterable' => true,
        ]);

        $this->addColumn([
            'index' => 'contact_name',
            'label' => trans('admin::app.settings.warehouses.index.datagrid.contact-name'),
            'type' => 'string',
            'searchable' => true,
            'filterable' => true,
            'sortable' => true,
        ]);

        $this->addColumn([
            'index' => 'contact_emails',
            'label' => trans('admin::app.settings.warehouses.index.datagrid.contact-emails'),
            'type' => 'string',
            'searchable' => true,
            'filterable' => true,
            'sortable' => true,
            'closure' => fn ($row) => $this->formatContactColumn($row->contact_emails),
        ]);

        $this->addColumn([
            'index' => 'contact_numbers',
            'label' => trans('admin::app.settings.warehouses.index.datagrid.contact-numbers'),
            'type' => 'string',
            'sortable' => false,
            'closure' => fn ($row) => $this->formatContactColumn($row->contact_numbers),
        ]);

        $this->addColumn([
            'index' => 'products',
            'label' => trans('admin::app.settings.warehouses.index.datagrid.products'),
            'type' => 'string',
            'sortable' => true,
            'filterable' => false,
        ]);

        $this->addColumn([
            'index' => 'created_at',
            'label' => trans('admin::app.settings.warehouses.index.datagrid.created-at'),
            'type' => 'date',
            'searchable' => true,
            'filterable' => true,
            'filterable_type' => 'date_range',
            'sortable' => true,
            'closure' => function ($row) {
                return core()->formatDate($row->created_at);
            },
        ]);
    }

    /**
     * Prepare actions.
     *
     * @return void
     */
    public function prepareActions()
    {
        if (bouncer()->hasPermission('settings.inventory.warehouse')) {
            $this->addAction([
                'icon' => 'icon-eye',
                'title' => trans('admin::app.settings.warehouses.index.datagrid.view'),
                'method' => 'GET',
                'url' => function ($row) {
                    return route('admin.settings.warehouses.view', $row->id);
                },
            ]);
        }

        if (bouncer()->hasPermission('settings.inventory.warehouse.edit')) {
            $this->addAction([
                'icon' => 'icon-edit',
                'title' => trans('admin::app.settings.warehouses.index.datagrid.edit'),
                'method' => 'GET',
                'url' => function ($row) {
                    return route('admin.settings.warehouses.edit', $row->id);
                },
            ]);
        }

        if (bouncer()->hasPermission('settings.inventory.warehouse.delete')) {
            $this->addAction([
                'icon' => 'icon-delete',
                'title' => trans('admin::app.settings.warehouses.index.datagrid.delete'),
                'method' => 'DELETE',
                'url' => function ($row) {
                    return route('admin.settings.warehouses.delete', $row->id);
                },
            ]);
        }
    }

    /**
     * Render a JSON contact column (`contact_emails`, `contact_numbers`) for a grid cell.
     *
     * A warehouse stores these as a list of bare scalars -- `["audit@example.test"]`,
     * `["+966500000000"]` -- while a person stores a list of `{"value": ..., "label": ...}` pairs.
     * The previous `collect($items)->pluck('value')` read each element through `data_get()`, which
     * returns null for a scalar, so a warehouse row plucked to `[null]` and rendered an empty cell in
     * every row. Both shapes are therefore read explicitly, and the value alone is shown, which is
     * what the rest of the grid does with a labelled entry; the label stays in the exported file.
     *
     * The value is returned as plain text: the datagrid gives it bidi isolation itself, so a phone
     * number keeps its `+` on the left in an `ar` interface. Sorting and filtering resolve against
     * the database column, never against this rendered value, so neither is affected.
     */
    protected function formatContactColumn(mixed $value): string
    {
        $items = json_decode((string) $value, true);

        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($items)) {
            return '';
        }

        return collect($items)
            ->map(function ($item) {
                if (! is_array($item)) {
                    return is_scalar($item) ? (string) $item : '';
                }

                return is_scalar($item['value'] ?? null) ? (string) $item['value'] : '';
            })
            ->filter(fn (string $item) => $item !== '')
            ->implode(', ');
    }
}
