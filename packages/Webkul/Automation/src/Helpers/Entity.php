<?php

namespace Webkul\Automation\Helpers;

use Webkul\Attribute\Repositories\AttributeRepository;
use Webkul\EmailTemplate\Repositories\EmailTemplateRepository;

class Entity
{
    /**
     * Create a new repository instance.
     *
     * @return void
     */
    public function __construct(
        protected AttributeRepository $attributeRepository,
        protected EmailTemplateRepository $emailTemplateRepository
    ) {}

    /**
     * Returns events to match for the entity
     *
     * @return array
     */
    public function getEvents()
    {
        $entities = config('workflows.trigger_entities');

        $events = [];

        foreach ($entities as $key => $entity) {
            $object = app($entity['class']);

            $translatedEvents = [];

            foreach ($entity['events'] as $event) {
                $event['name'] = $this->translateEventName($event['name']);

                $translatedEvents[] = $event;
            }

            $events[$key] = [
                'id' => $key,
                'name' => $this->translateEntityName($key, $entity['name']),
                'events' => $translatedEvents,
            ];
        }

        return $events;
    }

    /**
     * The label a screen shows for a trigger entity.
     *
     * `Config/workflows.php` is merged with `mergeConfigFrom()`, so a `trans()` there would freeze to
     * whichever locale happened to be active when the config was cached. The config therefore stays
     * the English source of truth, and the label is translated here, where the screens read it: the
     * workflow and webhook entity pickers and the email-template placeholder menu.
     *
     * Only the label moves. `getEvents()` returns the machine identifier separately in `id` (and every
     * event keeps its own `event`), and that — never the label — is what a form submits and what a
     * saved workflow stores, so translating the label cannot change what is written to the database.
     * A locale with no translation of its own falls back to the English name held in the config.
     */
    protected function translateEntityName(string $identifier, string $name): string
    {
        $key = 'admin::app.automation.entities.'.$identifier;

        return app('translator')->has($key) ? trans($key) : $name;
    }

    /**
     * The label a screen shows for a trigger event.
     *
     * Keyed on the configured English name (`Created` -> `created`), so an event added to the config
     * without a translation keeps its English label instead of rendering a raw translation key.
     */
    protected function translateEventName(string $name): string
    {
        $key = 'admin::app.automation.events.'.strtolower($name);

        return app('translator')->has($key) ? trans($key) : $name;
    }

    /**
     * Returns conditions to match for the entity
     *
     * @return array
     */
    public function getConditions()
    {
        $entities = config('workflows.trigger_entities');

        $conditions = [];

        foreach ($entities as $key => $entity) {
            $object = app($entity['class']);

            $conditions[$key] = $object->getConditions();
        }

        return $conditions;
    }

    /**
     * Returns workflow actions
     *
     * @return array
     */
    public function getActions()
    {
        $entities = config('workflows.trigger_entities');

        $conditions = [];

        foreach ($entities as $key => $entity) {
            $object = app($entity['class']);

            $conditions[$key] = $object->getActions();
        }

        return $conditions;
    }

    /**
     * Returns placeholders for email templates
     *
     * @return array
     */
    public function getEmailTemplatePlaceholders()
    {
        $entities = config('workflows.trigger_entities');

        $placeholders = [];

        foreach ($entities as $key => $entity) {
            $object = app($entity['class']);

            $entity['name'] = $this->translateEntityName($key, $entity['name']);

            $placeholders[] = $object->getEmailTemplatePlaceholders($entity);
        }

        return $placeholders;
    }
}
