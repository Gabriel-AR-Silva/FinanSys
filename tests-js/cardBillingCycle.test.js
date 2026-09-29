import assert from "node:assert/strict";
import test from "node:test";
import { suggestedFirstDueOn } from "../resources/js/Support/cardBillingCycle.js";

const nubank = { closing_day: 5, due_day: 12 };

test("day 4 is the last day of the current invoice", () => {
    assert.equal(suggestedFirstDueOn(nubank, "2026-09-04"), "2026-09-12");
});

test("day 5 is the first day of the next invoice", () => {
    assert.equal(suggestedFirstDueOn(nubank, "2026-09-05"), "2026-10-12");
});

test("day after closing remains in next invoice", () => {
    assert.equal(suggestedFirstDueOn(nubank, "2026-09-06"), "2026-10-12");
});

test("closing and due days are clamped for short months", () => {
    assert.equal(suggestedFirstDueOn({ closing_day: 31, due_day: 31 }, "2027-02-27"), "2027-03-31");
    assert.equal(suggestedFirstDueOn({ closing_day: 31, due_day: 31 }, "2027-02-28"), "2027-04-30");
});

test("due day before closing moves payment to following month", () => {
    assert.equal(suggestedFirstDueOn({ closing_day: 20, due_day: 10 }, "2026-09-19"), "2026-10-10");
});
