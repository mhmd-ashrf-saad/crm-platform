import { expect, test } from "../../fixtures/AdminFixtures";
import { buildEventData, eventdata, EventsPage } from "../../pages/settings/EventsPage";

test.describe('event management', () => {



  test('create event', async ({ adminPage }) => {
    const event = await new EventsPage(adminPage);
    await event.navigateToEvents();
    await event.createEvent(eventdata);

  })
  test('verify update event', async ({ adminPage }) => {
    const event = await new EventsPage(adminPage);
    await event.navigateToEvents();
    await event.searchByName(eventdata.name);
    await event.editEvents(eventdata);
  })
  test('verify delete event', async ({ adminPage }) => {
    const event = await new EventsPage(adminPage);
    await event.navigateToEvents();
    await event.searchByName(eventdata.name);
    await event.deleteEvent();
  })

  /**
   * Mass-delete the event this case creates, and prove the rest of the listing survived it.
   *
   * Two things make this case worth more than "the flash appeared":
   *
   * - The listing is compared by record id before and after, not by how many rows are visible. The
   *   old shape of this test ticked the grid header's select-all and then asserted the listing had
   *   become empty, which passed only when every record happened to be deletable and said nothing
   *   about *which* record had been chosen.
   * - The other events are asserted to be untouched, because the product refuses to delete an event
   *   that still carries campaigns: `EventController::massDestroy()` counts those, deletes the rest
   *   and answers with `partial-delete-warning` instead of `mass-delete-success`. A case that only
   *   checked for a flash would therefore pass on an instance whose events all carry campaigns even
   *   if the mass action had swallowed every record on the page — and fail on one whose events do
   *   not. Comparing ids, and asserting the exact success wording rather than "deleted successfully",
   *   holds either way.
   *
   * `massDeleteEvents` narrows the listing to this name, ticks that row's own checkbox and asserts
   * exactly one record id is selected before deleting; see `CoreLocators.massDelete()`.
   *
   * The id comparison covers one page of the listing, which is the whole of it on a stock instance
   * and is also exactly the scope a select-all can reach: the header's checkbox selects the records
   * the page is showing, never the ones on another page.
   */
  test('verify mass delete event', async ({ adminPage }) => {
    const event = new EventsPage(adminPage);
    const massDeleteEventData = buildEventData();

    const sortedIds = (ids: string[]) => [...ids].sort((left, right) => Number(left) - Number(right));

    await event.navigateToEvents();

    const beforeCreate = await event.eventRecordIds();

    await event.createEvent(massDeleteEventData);

    await event.navigateToEvents();

    const afterCreate = await event.eventRecordIds();
    const created = afterCreate.filter((id) => ! beforeCreate.includes(id));

    expect(created, "the event this case created must be the one new record in the listing").toHaveLength(1);
    expect(afterCreate, "creating one event must add exactly one record").toHaveLength(beforeCreate.length + 1);

    await event.massDeleteEvents(massDeleteEventData.name);

    /** The mass action's own wording: not the partial-delete warning, and not the none-deleted one. */
    await expect(event.flash("Events deleted successfully")).toBeVisible();

    await event.navigateToEvents();

    const afterDelete = await event.eventRecordIds();

    expect(
      sortedIds(afterDelete),
      "the events that were already in the listing must survive the mass delete, id for id"
    ).toEqual(sortedIds(beforeCreate));
  })
})