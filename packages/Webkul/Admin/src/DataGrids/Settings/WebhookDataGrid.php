<?php

namespace Webkul\Admin\DataGrids\Settings;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Webkul\Automation\Helpers\Entity;
use Webkul\DataGrid\DataGrid;

class WebhookDataGrid extends DataGrid
{
    /**
     * Prepare query builder.
     */
    public function prepareQueryBuilder(): Builder
    {
        $queryBuilder = DB::table('webhooks')
            ->addSelect(
                'webhooks.id',
                'webhooks.name',
                'webhooks.entity_type',
                'webhooks.end_point',
            );

        $this->addFilter('id', 'webhooks.id');
        $this->addFilter('entity_type', 'webhooks.entity_type');

        return $queryBuilder;
    }

    /**
     * Prepare Columns.
     */
    public function prepareColumns(): void
    {
        /**
         * The entity labels the create and edit forms already show, keyed by the identifier the
         * table stores. `getEvents()` is the same source those forms read, so the listing and the
         * form cannot label the same entity differently.
         *
         * Only the rendered label moves. The column still sorts, searches and filters on
         * `webhooks.entity_type`, and the dropdown filter below submits the identifier as its
         * value, so nothing starts matching a row by its translated name.
         */
        $entityLabels = collect(app(Entity::class)->getEvents())->pluck('name', 'id');

        $this->addColumn([
            'index' => 'id',
            'label' => trans('admin::app.settings.webhooks.index.datagrid.id'),
            'type' => 'string',
            'searchable' => true,
            'filterable' => true,
            'sortable' => true,
        ]);

        $this->addColumn([
            'index' => 'name',
            'label' => trans('admin::app.settings.webhooks.index.datagrid.name'),
            'type' => 'string',
            'searchable' => true,
            'filterable' => true,
            'sortable' => true,
        ]);

        $this->addColumn([
            'index' => 'entity_type',
            'label' => trans('admin::app.settings.webhooks.index.datagrid.entity-type'),
            'type' => 'string',
            'searchable' => true,
            'filterable' => true,
            'filterable_type' => 'dropdown',
            'filterable_options' => $entityLabels
                ->map(fn ($label, $identifier) => ['value' => $identifier, 'label' => $label])
                ->values()
                ->all(),
            'sortable' => true,
            'closure' => fn ($row) => $entityLabels[$row->entity_type] ?? $row->entity_type,
        ]);

        $this->addColumn([
            'index' => 'end_point',
            'label' => trans('admin::app.settings.webhooks.index.datagrid.end-point'),
            'type' => 'string',
            'searchable' => true,
            'filterable' => true,
            'sortable' => true,
        ]);
    }

    /**
     * Prepare actions.
     */
    public function prepareActions(): void
    {
        if (bouncer()->hasPermission('settings.automation.webhooks.edit')) {
            $this->addAction([
                'index' => 'edit',
                'icon' => 'icon-edit',
                'title' => trans('admin::app.settings.webhooks.index.datagrid.edit'),
                'method' => 'GET',
                'url' => fn ($row) => route('admin.settings.webhooks.edit', $row->id),
            ]);
        }

        if (bouncer()->hasPermission('settings.automation.webhooks.delete')) {
            $this->addAction([
                'index' => 'delete',
                'icon' => 'icon-delete',
                'title' => trans('admin::app.settings.webhooks.index.datagrid.delete'),
                'method' => 'DELETE',
                'url' => fn ($row) => route('admin.settings.webhooks.delete', $row->id),
            ]);
        }
    }
}
