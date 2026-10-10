# Fluent Support Merge

An administrator tool for merging duplicate [Fluent Support](https://github.com/WPManageNinja/fluent-support) customer records in WordPress. It is useful when an emailed ticket and a portal ticket belong to the same person but were attached to different customer records.

The merge is always a deliberate admin action. This plugin does not merge customers during login or change how the customer portal finds tickets.

## Features

- Search for two customer records by name, email address, or customer ID.
- See each record's WordPress account link and ticket count.
- Choose which record to keep and review the direction and tickets before confirming.
- Use Fluent Support's customer merge routine to move tickets and related data. Its merge hook runs for add-ons, and reassigned ticket access hashes are rotated.
- Access the tool from **Fluent Support → Merge customers** or from a Fluent Support customer profile.

## Requirements

- WordPress with Fluent Support installed and active.
- PHP 7.4 or newer.
- A WordPress administrator account with the `manage_options` capability.
- A Fluent Support build containing `FluentSupport\App\Services\EmailClaimService::absorb()`. The add-on was built against the Fluent Support 2.4.0 codebase containing that routine; compatibility with other builds should be checked before installation.



## Installation

1. Place the `fluent-support-customer-merge` folder in `wp-content/plugins/`, or ZIP that folder and upload it through **Plugins → Add New → Upload Plugin**.
2. Activate **Fluent Support Customer Merge**.
3. Open **Fluent Support → Merge customers** in the WordPress admin area.

## Merge two customers

1. Search for the first customer, then search for the second. Selecting the first record suggests other records with the same email address.
2. Select **which customer record to keep**. The other record is the source and will be merged into it.
3. Select **Review this merge**. Check the direction, ticket counts, and titles of the tickets that will move.
4. Tick the confirmation box and select **Confirm merge**.

The retained record keeps its customer ID and WordPress account link. Fluent Support's merge routine moves tickets, conversations, attachments, activity, and customer metadata, and removes the source record when it is empty. Ticket links issued before the ownership change are invalidated when their access hashes rotate.

## Merge rules

The plugin rechecks these conditions when the confirmation form is submitted:

- The source and retained records must be different customer records with the same email address.
- The retained record must be linked to an existing WordPress user whose account uses that email address.
- The source may be unlinked, linked to a deleted WordPress user, or linked to the same WordPress user as the retained record.
- A source linked to a different existing WordPress user cannot be merged with this tool.

Only administrators can search customer records or submit a merge. The confirmation request is protected by a WordPress nonce. No customer records are changed during search or review.

## License

GPL-3.0. See the [GNU General Public License, version 3](https://www.gnu.org/licenses/gpl-3.0.html).
