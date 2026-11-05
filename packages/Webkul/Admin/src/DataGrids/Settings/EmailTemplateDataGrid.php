<?php

namespace Webkul\Admin\DataGrids\Settings;

use Illuminate\Support\Facades\DB;
use Webkul\DataGrid\DataGrid;

class EmailTemplateDataGrid extends DataGrid
{
    /**
     * Prepare query builder.
     */
    public function prepareQueryBuilder()
    {
        $queryBuilder = DB::table('email_templates')
            ->addSelect(
                'email_templates.id',
                'email_templates.name',
                'email_templates.subject',
            );

        $this->addFilter('id', 'email_templates.id');

        return $queryBuilder;
    }

    /**
     * Add columns.
     *
     * @return void
     */
    public function prepareColumns()
    {
        $this->addColumn([
            'index' => 'id',
            'label' => trans('admin::app.settings.email-template.index.datagrid.id'),
            'type' => 'string',
            'sortable' => true,
            'searchable' => true,
            'filterable' => true,
        ]);

        $this->addColumn([
            'index' => 'name',
            'label' => trans('admin::app.settings.email-template.index.datagrid.name'),
            'type' => 'string',
            'sortable' => true,
            'searchable' => true,
            'filterable' => true,
        ]);

        $this->addColumn([
            'index' => 'subject',
            'label' => trans('admin::app.settings.email-template.index.datagrid.subject'),
            'type' => 'string',
            'sortable' => true,
            'closure' => fn ($row) => $this->displaySubject($row->subject),
        ]);
    }

    /**
     * Render a stored subject for the listing.
     *
     * The seeded subjects carry automation placeholders such as `{%activities.title%}`. Printed as
     * they are stored, the English namespace sits beside Arabic chrome and reads as a leftover that
     * was never translated, so the listing swaps each placeholder for the label of the field it
     * stands for, in brackets.
     *
     * Only the listing is changed. The stored subject, and with it the string the automation engine
     * substitutes into at send time, stays byte for byte as it was; both the `{%entity.attribute%}`
     * and `{% entity.attribute %}` spellings the engine accepts are matched here. A placeholder the
     * locale has no label for is printed as it stands rather than replaced with a guess.
     */
    protected function displaySubject(string $subject): string
    {
        return preg_replace_callback(
            '/\{%\s*([a-zA-Z_][a-zA-Z0-9_]*)\.([a-zA-Z0-9_]+)\s*%\}/',
            function (array $matches): string {
                foreach (['attributes.'.$matches[2], 'entities.'.$matches[1]] as $labelKey) {
                    if (trans()->has('admin::app.automation.'.$labelKey)) {
                        return '['.trans('admin::app.automation.'.$labelKey).']';
                    }
                }

                return $matches[0];
            },
            $subject
        ) ?? $subject;
    }

    /**
     * Prepare actions.
     *
     * @return void
     */
    public function prepareActions()
    {
        if (bouncer()->hasPermission('settings.automation.email_templates.edit')) {
            $this->addAction([
                'index' => 'delete',
                'icon' => 'icon-edit',
                'title' => trans('admin::app.settings.email-template.index.datagrid.edit'),
                'method' => 'GET',
                'url' => fn ($row) => route('admin.settings.email_templates.edit', $row->id),
            ]);
        }

        if (bouncer()->hasPermission('settings.automation.email_templates.delete')) {
            $this->addAction([
                'index' => 'delete',
                'icon' => 'icon-delete',
                'title' => trans('admin::app.settings.email-template.index.datagrid.delete'),
                'method' => 'DELETE',
                'url' => fn ($row) => route('admin.settings.email_templates.delete', $row->id),
            ]);
        }
    }
}
