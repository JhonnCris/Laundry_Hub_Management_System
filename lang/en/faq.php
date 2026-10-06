<?php

return [
    'button' => 'Help and guides',
    'title' => 'Help & guides',
    'intro' => 'Step-by-step guides, answers to common questions and a short tour of how the system works.',
    'tabs' => ['guide' => 'Step-by-step', 'faq' => 'FAQ', 'system' => 'System guide'],
    'close' => 'Close',

    'staff_guide' => [
        ['title' => '1. Clock in', 'steps' => [
            'Open Attendance Tracking. It is the first screen you see after logging in.',
            'Press Clock in, then confirm.',
            'Manage Customer and Transactions unlock. Press "Go to Manage Customer" to continue.',
        ]],
        ['title' => '2. Find or add a customer', 'steps' => [
            'Open Manage Customer and type a name or phone number in the search box.',
            'New customer? Press Add New Customer, fill in the first name, last name and phone, then save.',
            'Press Edit to update a customer\'s phone or email. Names are locked to protect past records.',
            'Press Do Laundry to start an order for that customer.',
        ]],
        ['title' => '3. Record a transaction', 'steps' => [
            'Choose Drop Off (staff washes it) or Self Service (customer uses a machine).',
            'Drop Off: pick a basket, count the garments, enter the weight and choose the service type.',
            'Self Service: enter the weight, pick a machine, the cycle time and the machine service.',
            'Add detergent or Downy, and any snacks or drinks, if the customer wants them.',
            'Check the Summary on the right, then press Save Transaction.',
        ]],
        ['title' => '4. Take payment and give the receipt', 'steps' => [
            'Enter the cash received. The change is shown automatically.',
            'Press Confirm payment to save the order and open the receipt.',
            'Press Print receipt, or Email receipt if the customer has an email address.',
            'Press New Transaction to continue with the same customer.',
        ]],
        ['title' => '5. Track the laundry', 'steps' => [
            'Open Active Laundry. Every current order is listed with its status.',
            'Press the green button to move an order forward: Processing, then Ready for pickup, then Claimed.',
            'When an order becomes Ready for pickup the customer is notified automatically.',
            'Pressed the wrong status? Use Undo. A Claimed order cannot be undone.',
            'Press History to see orders that were already claimed.',
        ]],
        ['title' => '6. Cancel an order', 'steps' => [
            'In Active Laundry press the red Cancel order button.',
            'Type a short reason and confirm.',
            'The order leaves the sales totals and its stock is returned. Only staff can cancel orders.',
        ]],
        ['title' => '7. Look up sales and receipts', 'steps' => [
            'Open Sales Record and choose Day, Week or Month, or press Show all.',
            'Press View receipt to open, print or email a receipt again.',
        ]],
        ['title' => '8. Stock and baskets', 'steps' => [
            'Open Stocks, then Inventory to see what is in stock. Low items have a red mark.',
            'Press Notify admin on a low item so the admin sees it in their alerts.',
            'Press Archive item to remove expired, spoiled or damaged stock.',
            'Use the dropdown above the baskets to show only In use or Available baskets. Press Add basket to register a new one.',
        ]],
        ['title' => '9. Machines', 'steps' => [
            'Open Manage Machines to see which machines are free and how much time is left on the ones in use.',
            'Press Set maintenance on a broken machine so it cannot be chosen. Press Set available when it is fixed.',
        ]],
        ['title' => '10. Clock out', 'steps' => [
            'When your shift ends open Attendance Tracking and press Clock out.',
            'Manage Customer and Transactions lock again until your next shift.',
        ]],
    ],

    'staff_faq' => [
        ['q' => 'Why are Manage Customer and Transactions locked?', 'a' => 'You can only add customers and record transactions while clocked in. Open Attendance Tracking and press Clock in. After clocking out they lock again.'],
        ['q' => 'I made a mistake on an order. What do I do?', 'a' => 'If it is only the status, press Undo in Active Laundry. If the whole order is wrong, press the red Cancel order button and record it again.'],
        ['q' => 'The customer has no email, so there is no Email receipt button.', 'a' => 'Open Manage Customer, press Edit on that customer and add an email address. Then open the receipt again from Sales Record.'],
        ['q' => 'The payment says the cash is less than the total.', 'a' => 'The cash received must be equal to or more than the amount due. Enter the full amount the customer handed over.'],
        ['q' => 'I cannot pick a basket.', 'a' => 'Only available baskets are listed. Open Inventory and check the baskets table, or press Add basket to register another one.'],
        ['q' => 'An item is running low. Who tells the admin?', 'a' => 'Press Notify admin on that item in Inventory. The admin sees it as a red urgent alert and records the new stock.'],
        ['q' => 'A machine is not in the list.', 'a' => 'Only machines that are available can be chosen. A machine in use or in maintenance is hidden until it is free.'],
        ['q' => 'How do I change the language, theme or text size?', 'a' => 'Press your name at the bottom of the left menu, then choose Display settings or Language.'],
        ['q' => 'I forgot my password.', 'a' => 'Log out, press "Forgot your password?" on the login page, or ask the admin to set a new one in Manage Users.'],
    ],

    'admin_guide' => [
        ['title' => '1. Read the dashboard', 'steps' => [
            'Pick a From and To date and press Filter to change the range.',
            'Click Active laundry, Low stock or Machines to see the details behind each number.',
            'Check Alerts: red items are urgent, blue items are for your information.',
        ]],
        ['title' => '2. Sales & Summary', 'steps' => [
            'Choose the date range, then use Show to switch between all activity, revenue, expenses and cancelled orders.',
            'Press Export, choose the report and PDF or CSV, then confirm.',
            'Cancelled orders are only shown here for reference. They are not counted in sales.',
        ]],
        ['title' => '3. Manage Inventory', 'steps' => [
            'Items that are low or out of stock have a red badge and a red edge.',
            'Press Edit on an item to change its name, category, unit, price, quantity or low-stock level.',
            'Press Add item to create a new product.',
            'For new deliveries use Stock Receiving instead, so the invoice is recorded.',
        ]],
        ['title' => '4. Stock Receiving', 'steps' => [
            'Press Record receipt.',
            'Enter the invoice number, date, supplier, the item, the quantity on the invoice and the quantity you really received.',
            'Add the invoice total if you want it logged as an expense, then confirm.',
        ]],
        ['title' => '5. Manage Users', 'steps' => [
            'New sign-ups are Pending and cannot log in until you approve them.',
            'Press Approve to make them staff, or Edit to choose Staff or Admin.',
            'Use Add user to create an account yourself, and Edit to reset a password.',
        ]],
    ],

    'admin_faq' => [
        ['q' => 'A new person registered but cannot log in.', 'a' => 'That is intended. Open Manage Users, find the Pending account and press Approve (or Edit and choose Staff). They can log in right after.'],
        ['q' => 'Why can I not cancel an order?', 'a' => 'Only staff can cancel orders. Admins can review cancelled orders in Sales & Summary.'],
        ['q' => 'Edit or Stock Receiving: which should I use?', 'a' => 'Use Edit to correct a mistake. Use Stock Receiving when goods were delivered, so the invoice and cost are recorded.'],
        ['q' => 'What do the alert colours mean?', 'a' => 'Red is urgent and needs action, such as low stock or a machine in maintenance. Blue is for your information.'],
        ['q' => 'How do I get a report?', 'a' => 'Press Export on the Dashboard or on Sales & Summary, pick the report and choose PDF or CSV.'],
        ['q' => 'How do I change the language?', 'a' => 'Press your name at the bottom of the left menu, then Language.'],
    ],

    'system' => [
        ['title' => 'Colours and badges', 'items' => [
            'Red means urgent or an error. Blue means information. Green means success.',
            'A red "Low stock" or "Out of stock" badge means the item needs restocking.',
        ]],
        ['title' => 'Accounts and roles', 'items' => [
            'Staff run the shop: customers, transactions, laundry, stock and machines.',
            'Admins manage reports, inventory, receiving and users.',
            'New accounts are Pending until an admin approves them.',
        ]],
        ['title' => 'Settings', 'items' => [
            'Press your name at the bottom of the left menu for profile, password, display and language settings.',
            'Display settings change the theme and the text size. Language switches the system text, for example to Filipino.',
        ]],
        ['title' => 'Long lists', 'items' => [
            'Tables show 10 rows at a time. Use Prev and Next under the table to change page.',
        ]],
        ['title' => 'Still stuck?', 'items' => [
            'Ask the shop owner or an admin. If something shows a red error message, tell them the exact words.',
        ]],
    ],
];
