# Pricing Plans Tags Save Fix

## Problem

On some sites, the Directorist Pricing Plans admin screen showed an error/cross
state for the Tags field.

When Tags was enabled and also hidden from the pricing plan, clicking Update did
not persist the change. After reload, the setting reverted to the previous
state.

## How I detected it

I checked the real Directorist builder data for the affected directory type.
Directorist stores directory builder fields in term meta:

```text
taxonomy: atbdp_listing_types
meta key: submission_form_fields
```

The important value was the Tags preset field definition inside:

```text
submission_form_fields.fields
```

The affected directory stored the Tags field key as:

```text
tax_input[at_biz_dir-tags]
```

The normal Directorist/Pricing Plans-compatible key is:

```text
tax_input[at_biz_dir-tags][]
```

That missing `[]` was the problem.

## Why the setting did not save

Directorist Pricing Plans has hardcoded field-key mapping for Tags. Its admin
templates and listing type manager convert this key:

```text
tax_input[at_biz_dir-tags][]
```

into the Pricing Plans meta key:

```text
tag
```

Then the plan form can submit values like:

```text
form_fields[tag]
form_fields[hide_tag]
```

But when the builder data has the malformed key:

```text
tax_input[at_biz_dir-tags]
```

Pricing Plans does not map it to `tag`. The plan form then uses the wrong field
name, so `_tag` and `_hide_tag` are not saved/read consistently.

That is why the UI looked like the Update button did nothing.

## What I fixed

I added a small compatibility layer in Listing Tools:

```text
includes/compat/directorist-pricing-plans-tag-field-key-fix.php
```

The fix normalizes the Tags field key at runtime when Directorist reads the
builder data.

Main hooks:

```php
add_filter( 'get_term_metadata', 'dlt_pp_tag_field_key_fix_filter_submission_form_fields', 20, 5 );
add_filter( 'directorist_form_field_data', 'dlt_pp_tag_field_key_fix_normalize_field_data', 5 );
```

The `get_term_metadata` filter catches this exact builder meta read:

```text
submission_form_fields
```

only for Directorist listing type terms.

If the Tags field is found, the field definition is normalized to:

```text
field_key: tax_input[at_biz_dir-tags][]
widget_name: tag
widget_key: tag
```

After that, the existing Pricing Plans code can save and read:

```text
form_fields[tag]
form_fields[hide_tag]
```

normally.

## Why this is in Listing Tools

This is a site compatibility fix, not a pricing feature rewrite.

Pricing Plans already works when the builder data has the correct Tags key. The
bug appears when a directory stores the older or malformed key. Listing Tools is
the safest live-site place to normalize that data without editing Directorist or
Directorist Pricing Plans directly.

This is also separate from the other Pricing Plans feature in Listing Tools:

```text
Pricing plans dashboard views fix
```

That fix is for speed. It scopes heavy dashboard/modal rendering so it does not
slow down Elementor/editor pages.

This Tags save fix is for data compatibility. It normalizes only the Tags field
key so the Pricing Plans admin form can save correctly.

## What it does not change

The fix does not update or migrate:

- Listings
- Pricing plan posts
- Orders
- Payments
- Subscriptions
- Taxonomy terms
- Builder term meta in the database

It only changes the field definition returned to Directorist while the request
is running.

## Verification

After enabling Listing Tools version `2.2.7`, the runtime check returned:

```text
dlt_version=2.2.7
feature_loaded=yes
feature_enabled=yes
term_meta_tag_key=tax_input[at_biz_dir-tags][]
builder_field_key=tax_input[at_biz_dir-tags][]
admin_form_name=form_fields[tag]
plan_field={"key":"tag","label":"Tags (unlimited)","is_preset":true,"is_active":true,"hide_from_plan":true,"limit":-1}
```

That confirms the builder key is normalized, the Pricing Plans admin form uses
`form_fields[tag]`, and the plan API reads the Tags field as active and hidden.
