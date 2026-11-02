# Laundry Hub Management System — Test Cases

## Purpose

This manual acceptance test suite covers the main staff, admin, and customer-facing workflows. It complements, but does not replace, the automated Pest feature tests in `tests/`.

## Test setup and safety

- Run against a dedicated test/staging environment, never the live store database.
- Use accounts for each role: guest, pending user, staff, and admin.
- Prepare disposable customers, available and unavailable machines, active laundry services, detergent/Downy products, snacks/drinks, and inventory with known stock and prices.
- Keep the initial stock counts, service prices, machine rates, and cash balances so results can be checked.
- Clean up created test accounts, customers, orders, inventory changes, and files after the run where supported.
- Do not put credentials or production customer information in test evidence.
- Mark each case **Pass**, **Fail**, or **Blocked** and record the build, environment, tester, date, and actual result.

## Authentication and account access

| ID | Priority | Preconditions | Steps | Expected result |
|---|---|---|---|---|
| AUTH-01 | P1 | Signed out | Open the home page, then choose **Log in**. | Home page renders; login page opens and provides the expected authentication form. |
| AUTH-02 | P1 | Verified staff or admin account exists | Sign in with the correct email and password. | User is authenticated and taken to the correct role-specific page. |
| AUTH-03 | P1 | Sign-in page is open | Submit an incorrect password for an existing account. | Sign-in is rejected, an error is shown, and the user remains unauthenticated. |
| AUTH-04 | P1 | Signed out; unused email available | Register a new account and submit valid details. | Account is created as pending approval; it does not receive staff/admin access automatically. |
| AUTH-05 | P1 | Pending account exists | Sign in and attempt to open the staff dashboard or staff API. | Access is denied or the pending-approval page is shown; protected staff operations are unavailable. |
| AUTH-06 | P1 | Staff account exists | Attempt to open the admin page and call an admin-only operation. | Staff cannot access admin screens or admin-only endpoints. |
| AUTH-07 | P1 | Admin account exists | Open the staff dashboard URL and the admin dashboard URL. | Admin is routed to the admin experience and does not receive staff-only access. |
| AUTH-08 | P2 | Signed-in user | Sign out, then use browser Back to revisit a protected page. | User is signed out; protected information is not available from the back-forward cache. |

## Staff attendance and customers

| ID | Priority | Preconditions | Steps | Expected result |
|---|---|---|---|---|
| STAFF-01 | P1 | Staff account is off duty | Open Attendance Tracking and clock in. | A new open shift is recorded; status shows on duty; Clock in is disabled and Clock out is enabled. |
| STAFF-02 | P1 | Staff has an open shift | Try to clock in again before clocking out. | A second open shift is not created; the active shift remains unchanged. |
| STAFF-03 | P1 | Staff has an open shift | Clock out, then clock in again on the same work date. | First shift is completed; a new shift is created; Clock in is disabled during the new shift. |
| STAFF-04 | P1 | Staff is off duty | Try to add a customer or save a transaction. | The operation is blocked until staff clocks in. |
| STAFF-05 | P1 | Staff is clocked in | Add a customer with valid first name, last name, and contact number. | Customer is saved and appears in the customer list. |
| STAFF-06 | P1 | Staff is clocked in; customer already exists | Add the same customer using a differently formatted version of the same phone number. | Duplicate is identified and not silently created. |
| STAFF-07 | P2 | Staff is clocked in; a different customer has the same phone number | Start customer creation and complete the same-phone confirmation flow. | Creation proceeds only after the confirmation; existing customer data remains unchanged. |
| STAFF-08 | P2 | Customer exists | Edit the customer’s contact information and reload the list. | Updated details persist and are reflected in customer search/results. |
| STAFF-09 | P1 | Staff is clocked in | Select a customer, start a transaction, then change the selected customer. | Transaction context displays the selected customer; changing it updates the displayed customer without creating an order. |

## Transactions, services, and inventory sales

| ID | Priority | Preconditions | Steps | Expected result |
|---|---|---|---|---|
| TX-01 | P1 | Staff is clocked in; active service exists | Select a customer; create a Drop Off order; choose a basket, service, and load weight. | Summary shows the correct service, basket, and load; total uses the configured service price. |
| TX-02 | P1 | Staff is clocked in; snacks/drinks are in stock | Create a purchase with no laundry service; add an item and quantity; pay and save. | Purchase is saved without a laundry queue entry; total is correct and stock decreases by the purchased quantity. |
| TX-03 | P1 | Staff is clocked in; at least two detergent/Downy products are in stock | Select multiple products, such as 5 Downy and 3 Ariel; review and save the order. | Each product has its own quantity; summary and total include both line items; each item’s stock decreases by its selected quantity. |
| TX-04 | P1 | Staff is clocked in; detergent inventory is available | In Drop Off workflow, select more than one detergent/Downy item and add a service. | Multiple selected products are included in the same transaction; configured service and item prices are used. |
| TX-05 | P1 | Washer is available; staff is clocked in | In Self Service, select a washer and add detergent/Downy products. | Detergent selector is visible; order total includes the current washer rate and each selected product. |
| TX-06 | P1 | Dryer is available; staff is clocked in | In Self Service, select only a dryer. | Detergent/Downy selector is hidden, and no detergent item is included in the summary or saved order. |
| TX-07 | P1 | Washer and dryer are available | Select both, then deselect the washer so only the dryer remains; select the washer again. | Detergent/Downy selector follows the current selection: visible with a washer, hidden for dryer-only. Hidden selections are not charged or submitted. |
| TX-08 | P1 | Washer and dryer are available; valid rates configured | Select multiple machines and select a valid dryer duration. | Each machine charge uses its configured size/type/time rate; order summary and saved total match the rates. |
| TX-09 | P1 | Machine has an active order or is unavailable | Try to select it for a new Self Service order. | Unavailable machine cannot be selected; save is prevented if no eligible machine is selected. |
| TX-10 | P1 | Stock quantity is lower than requested quantity | Add more units of an inventory product than are available and submit. | Transaction is rejected with a clear stock error; no partial order or stock deduction is saved. |
| TX-11 | P1 | Staff has prepared a valid transaction | Submit payment with cash below the amount due. | Confirmation remains disabled or submission is rejected; no transaction is saved. |
| TX-12 | P1 | Staff has prepared a valid transaction | Submit a valid transaction, then resubmit using the same payment token. | Exactly one order and one sale are recorded; duplicate submission does not charge or deduct stock twice. |
| TX-13 | P2 | Staff has prepared an order with detergent and/or snacks | Review the payment summary and receipt. | Every selected product and quantity appears with its correct line amount; grand total and change are correct. |
| TX-14 | P1 | Staff has a service, detergent/Downy, or snack product selected | Change the order workflow or deselect the relevant items; then review total and save payload behavior. | Items from the inactive workflow are excluded; displayed total matches the items actually submitted. |

## Laundry processing, cancellation, and staff operations

| ID | Priority | Preconditions | Steps | Expected result |
|---|---|---|---|---|
| OPS-01 | P1 | A Drop Off order has been saved | Move order through Pending → Processing → Ready for pickup → Claimed. | Only valid forward transitions are accepted; status and history update after each step. |
| OPS-02 | P1 | Order is at a valid status | Try an invalid status transition or attempt to cancel through the status-change action. | Invalid transition is rejected; cancellation remains available only through the cancel workflow. |
| OPS-03 | P1 | Drop Off order is marked unpaid | Mark it ready, then collect payment when claiming it. | Order remains unpaid until collection; successful payment records the correct amount and releases the order. |
| OPS-04 | P1 | Active order contains inventory items or detergent | Cancel the order with a valid reason. | Order is archived as cancelled, the reason is retained, and all deducted inventory is returned once. |
| OPS-05 | P1 | Order is already claimed | Attempt to cancel it. | Cancellation is rejected; order and stock remain unchanged. |
| OPS-06 | P2 | Claimed orders exist | Open laundry history and search/browse records. | Claimed orders appear in history; active or unclaimed orders do not appear as completed history. |
| OPS-07 | P1 | Available basket exists | Assign basket to an open order, then try to assign it to another order. | Basket is marked in use for the first order and is not offered as available to a second order. |
| OPS-08 | P2 | Stock item exists | Archive part of its stock with a valid reason; try archiving more than the remaining quantity. | Valid archive movement is recorded; over-archive or zero quantity is rejected; archive history shows quantity and reason. |
| OPS-09 | P2 | Machine is not currently in use | Change availability to maintenance, then restore it to available. | Machine status updates and the machine cannot be picked while unavailable. |
| OPS-10 | P2 | Staff account is clocked out | Attempt to use customer and transaction operations after clock-out. | Staff duty-gated operations are blocked again. |

## Admin operations and reporting

| ID | Priority | Preconditions | Steps | Expected result |
|---|---|---|---|---|
| ADMIN-01 | P1 | Admin is signed in; activity and sales exist | Open Dashboard and filter by a date range. | Counts, activity, charts, and service mix reflect the selected date range. |
| ADMIN-02 | P1 | Admin is signed in | Export a report as CSV, then PDF; try an unsupported file type if available. | Supported files download with the expected report data and export audit record; unsupported type is rejected. |
| ADMIN-03 | P1 | Admin is signed in | Add a product category and inventory item with valid details. | Category/item is saved and appears in the relevant inventory and staff selectors. |
| ADMIN-04 | P1 | Inventory item exists | Edit the item’s details and on-hand quantity. | Changes persist; quantity changes are reflected in stock movement history. |
| ADMIN-05 | P1 | Inventory item has known stock | Add a stock receipt in multiple parts and confirm each part. | On-hand stock increases once per confirmed part and a stock movement is recorded for each receipt. |
| ADMIN-06 | P1 | Unstocked receipt exists | Void the receipt, then attempt to void a receipt already added to inventory. | Unstocked receipt can be voided with the matching expense handled; stocked receipt cannot be voided in a way that corrupts stock. |
| ADMIN-07 | P1 | Service exists | Change service price and mark it inactive; refresh staff transaction screen. | New price is used for future orders; inactive service is no longer offered to staff. |
| ADMIN-08 | P1 | Machine/rates are configurable | Add/edit a machine and set washer/dryer rates; try invalid or duplicate configurations. | Valid configuration persists and drives staff pricing; invalid/duplicate values are rejected. |
| ADMIN-09 | P1 | Pending user exists | Approve the user and verify staff access; reject another pending user with a reason. | Approved user is linked to the correct role; rejected user cannot access staff operations and rejection reason is retained. |
| ADMIN-10 | P1 | At least one bill exists | Record a partial payment, then pay the remaining balance. | Balance decreases accurately; each payment is recorded as an expense; bill status reflects outstanding/paid state. |
| ADMIN-11 | P2 | Admin is signed in | Add a one-off expense and void it; attempt to void a protected sale or stock row. | Expense is recorded and can be voided with an audit trail; protected entries cannot be removed through expense voiding. |
| ADMIN-12 | P2 | Admin is signed in; audit events exist | Open Activity Log, navigate pages, and review an event. | Events show correct actor/action/time and pagination does not duplicate or omit adjacent records. |
| ADMIN-13 | P1 | Staff and admin accounts exist | Call admin APIs as staff and staff APIs as a pending user. | Unauthorized role receives a forbidden/unauthenticated response; no protected data or mutation is returned. |

## Customer notifications and account settings

| ID | Priority | Preconditions | Steps | Expected result |
|---|---|---|---|---|
| EXTRA-01 | P2 | A Drop Off order is waiting for pickup and notification configuration is enabled | Open its signed notification link and opt in; try an altered/expired link. | Valid link permits the intended opt-in; invalid or expired signature is rejected. |
| EXTRA-02 | P2 | Signed-in user | Update profile details, change password, and update display/language preferences. | Valid changes persist after reload; invalid inputs are rejected with useful validation feedback. |
| EXTRA-03 | P2 | Low-stock item exists | Flag it from staff inventory and inspect admin alerts; attempt to flag an item that is not low. | Low-stock report appears once and resolves after restock; non-low item cannot be flagged. |
| EXTRA-04 | P2 | Staff and admin pages are available | Check dashboard and transaction screens at desktop and narrow/mobile widths. | Navigation, forms, selectors, modals, and tables remain readable and usable without obscured controls or horizontal clipping. |

## Existing automated coverage

The repository contains Pest feature tests for authentication, account approval, customer validation, attendance, transaction pricing, machine rates, cancellation, inventory, bills, alerts, history, and security hardening. Run the automated suite separately with:

```powershell
php artisan test --compact
```

Record the command exit code and test summary alongside manual execution results. A blocked test run is not a pass.
