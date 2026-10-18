# Accessibility audit: Demo City Council

**Standard:** WCAG 2.2 level AA
**Date:** October 2026
**Site:** Demo City Council on LocalGov Drupal 4.0.5 (Drupal 11.4) with the `demo_city` theme
**Result:** 10 issues found, all fixed. Automated checks now report 0 violations on 14 page states, at desktop and mobile sizes.

Demo City Council is fictional. This is a portfolio project, but the audit was done the way I would audit a real council website.

## How I tested

| Method | What it covers |
|---|---|
| axe-core 4.10 in Playwright, rules tagged WCAG 2.0, 2.1 and 2.2 A/AA plus best practice | 14 page states, each at 1280px and 390px wide. Includes form errors and confirmation pages, not just first page loads. Runs in CI on every push ([`tests/accessibility/a11y-check.js`](../tests/accessibility/a11y-check.js)). |
| Keyboard walkthrough | Tab order and the visibility of every focus style in the header, breadcrumbs and content. |
| Reflow at 320px | Checked 8 page types for horizontal scrolling (WCAG 1.4.10). None found. |
| Colour contrast | Measured every colour in the theme palette against white (WCAG 1.4.3). |
| Page titles, headings and language | Reviewed every page type (WCAG 2.4.2, 2.4.6, 3.1.1). |
| Forms | Error messages, labels, autocomplete and what happens after submitting (WCAG 3.3.1 to 3.3.3, 1.3.5, 4.1.3). |
| Documents | How PDFs are offered, and what a resident can do if they cannot use one. |

## Findings

Severity: **High** means some residents cannot complete a task. **Medium** means a task is harder than it should be. **Low** means a smaller problem or a best-practice gap.

| ID | Issue | WCAG | Severity | Fix | Where |
|---|---|---|---|---|---|
| A1 | The logo link's focus style was a thin 1px dashed line, much weaker than every other focus style. | 2.4.7 Focus Visible | Low | A 3px yellow outline, like the other header links. | `demo_city/css/overrides.css` |
| A2 | The search button said "Apply", which does not say what it does. | 2.4.6 Headings and Labels | Medium | Relabelled "Search" in the LocalGov search view. | `DemoContent::searchButton()` |
| A3 | Status and error messages had `role="contentinfo"`, so pages with a message had two "footer" landmarks. Flagged by axe-core on the form error pages. | 1.3.1 Info and Relationships | Medium | Theme template: errors use `role="alert"`, other messages use `role="status"`. | `demo_city/templates/misc/status-messages.html.twig` |
| A4 | Radio buttons built with Drupal's Form API showed the button on one line and its label on the next, so it was unclear which went with which. Buttons were 13px. | 1.3.1, 2.5.8 Target Size | Medium | Button and label side by side; button enlarged to 24px. | `demo_city/css/overrides.css` |
| A5 | Errors said "Full name field is required." in a list at the top, with no links to the fields and nothing next to the fields. | 3.3.1 Error Identification, 3.3.3 Error Suggestion | High | Enabled Inline Form Errors (error summary with links, plus a message at each field) and wrote specific messages, such as "Enter your full name". | `scripts/setup-site.sh`, `RequestForm` |
| A6 | After sending a request, the confirmation appeared but focus stayed at the top of the page. Screen reader users might not hear their reference number. | 4.1.3 Status Messages | High | Focus moves to the confirmation panel. | `accessible_formats/js/confirmation.js` |
| A7 | The home page title was "Demo City Council \| Demo City Council". | 2.4.2 Page Titled | Low | The home page title is now just the council name. | `demo_city.theme` |
| A8 | On publication pages, "This file may not be suitable for users of assistive technology" was read twice, once from LocalGov Publications and once from my notice. | Not a WCAG failure; repetition for screen reader users | Low | Theme template removes the duplicate. My notice keeps the sentence and adds the request link. | `demo_city/templates/media/media--document--publication.html.twig` |
| A9 | The base theme's orange (`#ed7522`) is 2.9:1 on white and its red (`#e4251b`) is 4.6:1, only just above the 4.5:1 minimum. | 1.4.3 Contrast (Minimum) | Medium (if used for text) | Replaced the palette: orange `#a8480c` is 5.8:1 and red `#b3261e` is 6.5:1. | `demo_city/css/variables.css` |
| A10 | There was no accessibility statement, which UK public sector websites must publish. | Public Sector Bodies Accessibility Regulations 2018 | High | Added a statement page, based on the GOV.UK sample, linked from the home page. | `DemoContent::aboutThisWebsite()` |

### Also found during testing

The "Request an accessible format" form accepted any file, including LocalGov's default SVG icons. That is not an accessibility issue, but it would let someone fill the request queue with nonsense. The form now only accepts the document types that show the notice, and a functional test checks that an image returns "Access denied".

## What passed

- The skip link is the first Tab stop on every page.
- No horizontal scrolling at 320px wide on any page type tested.
- Every page has a unique, descriptive title and `lang="en"`.
- Tables use captions and column headers.
- The bin day finder moves focus to its results after a search.
- The PDFs in the demo are tagged. Even so, the budget is published as HTML first, with the PDF as an extra.

## What automated testing cannot tell you

These checks catch a lot, but not everything. Before a real launch I would also:

1. Test with NVDA and JAWS on Windows, and VoiceOver on iPhone and Mac.
2. Check every page at 200% and 400% zoom.
3. Test with speech recognition software, such as Dragon.
4. Test the main journeys with residents who use assistive technology every day.
5. Review real council PDFs, which are often untagged scans, and convert the most used ones to HTML.
