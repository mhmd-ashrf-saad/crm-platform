<?php

namespace Webkul\Admin\DataGrids\Settings;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Webkul\DataGrid\DataGrid;

class RoleDataGrid extends DataGrid
{
    /**
     * Prepare query builder.
     */
    public function prepareQueryBuilder(): Builder
    {
        $queryBuilder = DB::table('roles')
            ->addSelect(
                'roles.id',
                'roles.name',
                'roles.description',
                'roles.permission_type'
            );

        /**
         * Scope the listing to the acting user's data scope. A `global` scope (or a full
         * administrator) returns null and sees every role; a `group` scope sees the roles created
         * within their group; an `individual` scope sees only the roles they created. In every case
         * the user's own assigned role is included, so they can always see the role they belong to.
         */
        if ($userIds = bouncer()->getAuthorizedUserIds()) {
            $ownRoleId = auth()->guard('user')->user()?->role_id;

            $queryBuilder->where(function ($query) use ($userIds, $ownRoleId) {
                $query->whereIn('roles.created_by', $userIds)
                    ->orWhere('roles.id', $ownRoleId);
            });
        }

        $this->addFilter('id', 'roles.id');
        $this->addFilter('name', 'roles.name');

        return $queryBuilder;
    }

    /**
     * Prepare Columns.
     */
    public function prepareColumns(): void
    {
        /**
         * The permission types the form offers, keyed by the identifier `roles.permission_type`
         * stores. The filter dropdown and the rendered cell share this one map, so the label a list
         * shows and the value it filters on cannot drift apart.
         */
        $permissionTypes = [
            'custom' => trans('admin::app.settings.roles.index.datagrid.custom'),
            'all' => trans('admin::app.settings.roles.index.datagrid.all'),
        ];

        $this->addColumn([
            'index' => 'id',
            'label' => trans('admin::app.settings.roles.index.datagrid.id'),
            'type' => 'string',
            'searchable' => true,
            'filterable' => true,
            'sortable' => true,
        ]);

        $this->addColumn([
            'index' => 'name',
            'label' => trans('admin::app.settings.roles.index.datagrid.name'),
            'type' => 'string',
            'searchable' => true,
            'filterable' => true,
            'sortable' => true,
        ]);

        $this->addColumn([
            'index' => 'description',
            'label' => trans('admin::app.settings.roles.index.datagrid.description'),
            'type' => 'string',
            'searchable' => true,
            'sortable' => false,
        ]);

        $this->addColumn([
            'index' => 'permission_type',
            'label' => trans('admin::app.settings.roles.index.datagrid.permission-type'),
            'type' => 'string',
            'searchable' => true,
            'filterable' => true,
            'filterable_type' => 'dropdown',
            'filterable_options' => collect($permissionTypes)
                ->map(fn ($label, $value) => ['label' => $label, 'value' => $value])
                ->values()
                ->all(),
            'sortable' => true,
            'closure' => fn ($row) => $permissionTypes[$row->permission_type] ?? $row->permission_type,
        ]);
    }

    /**
     * Prepare actions.
     */
    public function prepareActions(): void
    {
        if (bouncer()->hasPermission('settings.user.roles.edit')) {
            $this->addAction([
                'icon' => 'icon-edit',
                'title' => trans('admin::app.settings.roles.index.datagrid.edit'),
                'method' => 'GET',
                'url' => fn ($row) => route('admin.settings.roles.edit', $row->id),
            ]);
        }

        if (bouncer()->hasPermission('settings.user.roles.delete')) {
            $this->addAction([
                'icon' => 'icon-delete',
                'title' => trans('admin::app.settings.roles.index.datagrid.delete'),
                'method' => 'DELETE',
                'url' => fn ($row) => route('admin.settings.roles.delete', $row->id),
            ]);
        }
    }
}
