<?php
if (!defined('ABSPATH')) exit;

use MdsSupportingClasses\MdsColliveryService;
use MdsSupportingClasses\MdsCheckoutFields;

/**
 *  Enqueue SelectWoo + plugin script so Town/City Search works in admin profile.
 */
add_action('admin_enqueue_scripts', function ($hook) {
    if (!in_array($hook, ['profile.php', 'user-edit.php'], true)) return;

    $mds = MdsColliveryService::getInstance();
    if (!$mds || !$mds->isEnabled()) return;

    wp_enqueue_script('jquery');
    wp_enqueue_script('selectWoo');

    wp_enqueue_script(
        'mds-collivery-script',
        plugins_url('../script.js', __FILE__),
        ['jquery', 'selectWoo'],
        '1.0.0',
        true
    );

    // script.js expects woocommerce_params.ajax_url
    wp_localize_script('mds-collivery-script', 'woocommerce_params', [
        'ajax_url' => admin_url('admin-ajax.php'),
    ]);

    // Make admin profile behave like checkout for your script selectors
    wp_add_inline_script(
        'mds-collivery-script',
        "jQuery(function($){ $('body').addClass('colliveryfield'); });",
        'after'
    );
});

/**
 *  Output ONLY a hidden container with our <tr> rows (no headings),
 * then we inject those rows into Woo's existing billing/shipping address blocks.
 */
add_action('show_user_profile', 'mds_hidden_collivery_rows_for_wc_profile', 1);
add_action('edit_user_profile', 'mds_hidden_collivery_rows_for_wc_profile', 1);

function mds_hidden_collivery_rows_for_wc_profile($user)
{
    $mds = MdsColliveryService::getInstance();
    if (!$mds || !$mds->isEnabled()) return;

    $searchEnabled = $mds->isTownsSuburbsSearchEnabled();

    
    $billingTown   = get_user_meta($user->ID, 'billing_city', true);
    $billingSuburb = get_user_meta($user->ID, 'billing_suburb', true);
    $shippingTown   = get_user_meta($user->ID, 'shipping_city', true);
    $shippingSuburb = get_user_meta($user->ID, 'shipping_suburb', true);

    echo '<script>
        window.mds_profile_prefill = {
            searchEnabled: ' . ($searchEnabled ? 'true' : 'false') . ',
            billing: { town: ' . json_encode((string)$billingTown) . ', suburb: ' . json_encode((string)$billingSuburb) . ' },
            shipping:{ town: ' . json_encode((string)$shippingTown) . ', suburb: ' . json_encode((string)$shippingSuburb) . ' }
        };
    </script>';

    echo '<div id="mds-collivery-hidden-rows" style="display:none;">';

    echo '<div class="mds-block" data-target="billing"><table><tbody>';
    mds_render_collivery_rows($user->ID, 'billing', $searchEnabled);
    echo '</tbody></table></div>';

    echo '<div class="mds-block" data-target="shipping"><table><tbody>';
    mds_render_collivery_rows($user->ID, 'shipping', $searchEnabled);
    echo '</tbody></table></div>';

    echo '</div>';
}

/**
 *  Render rows:
 * - ALWAYS: location_type
 * - IF search enabled: town_city_search (Select2 search)
 * - IF search disabled: Town/City dropdown (billing_city/shipping_city) + Suburb dropdown
 *
 */
function mds_render_collivery_rows($user_id, $type, $searchEnabled)
{
    $mds = MdsColliveryService::getInstance();
    $collivery = $mds->returnColliveryClass();

    $builder   = new MdsCheckoutFields([]);
    $allFields = $builder->getCheckoutFields($type);

    // Keys in the exact visual order we want
    if ($searchEnabled) {
        $keys = ["{$type}_location_type", "{$type}_town_city_search"];
    } else {
        // IMPORTANT: this is a TOWN/CITY DROPDOWN (same meta key as Woo city)
        $keys = ["{$type}_location_type", "{$type}_city", "{$type}_suburb"];
    }

    // --- Search mode preselect (Select2) ---
    if ($searchEnabled && isset($allFields["{$type}_town_city_search"])) {
        $suburb_id = get_user_meta($user_id, "{$type}_suburb", true);

        if ($suburb_id && is_numeric($suburb_id)) {
            $suburb = $collivery->getSuburb((int)$suburb_id);

            if (is_array($suburb) && isset($suburb['name'], $suburb['town']['name'])) {
                $label = $suburb['name'] . ', ' . $suburb['town']['name'];

                // Must include selected option in markup
                $allFields["{$type}_town_city_search"]['options'] = [
                    '' => 'Please select',
                    (string)$suburb_id => $label,
                ];
            }
        }
    }

    // --- Non-search mode preselect (Town dropdown + Suburb dropdown) ---
    if (!$searchEnabled) {
        $saved_town   = (string) get_user_meta($user_id, "{$type}_city", true);
        $saved_suburb = (string) get_user_meta($user_id, "{$type}_suburb", true);

        // Ensure Town dropdown has the selected value
        if ($saved_town !== '' && isset($allFields["{$type}_city"])) {
            if (empty($allFields["{$type}_city"]['options']) || !is_array($allFields["{$type}_city"]['options'])) {
                $allFields["{$type}_city"]['options'] = ['' => 'Select town/city'];
            }

            if (!array_key_exists($saved_town, $allFields["{$type}_city"]['options'])) {
                // Fallback label (if options list is incomplete for any reason)
                $town_label = $saved_town;
                if (method_exists($collivery, 'getTown')) {
                    $t = $collivery->getTown((int)$saved_town);
                    if (is_array($t) && isset($t['name'])) $town_label = $t['name'];
                }
                $allFields["{$type}_city"]['options'][$saved_town] = $town_label;
            }
        }

        // Populate suburb dropdown with full list for the saved town (admin does not always trigger AJAX).
        if (isset($allFields["{$type}_suburb"])) {
            $town_id = null;
            if ($saved_town !== '') {
                if (is_numeric($saved_town)) {
                    $town_id = (int)$saved_town;
                } else {
                    $towns = $collivery->getTowns();
                    foreach ($towns as $item) {
                        if (isset($item['name'], $item['id']) && $item['name'] === $saved_town) {
                            $town_id = (int)$item['id'];
                            break;
                        }
                    }
                }
            }

            if ($town_id) {
                $suburbs = $collivery->getSuburbs($town_id);
                $options = ['' => 'Select suburb'];
                foreach ($suburbs as $item) {
                    if (isset($item['id'], $item['name'])) {
                        $options[(string)$item['id']] = $item['name'];
                    }
                }
                $allFields["{$type}_suburb"]['options'] = $options;
            } elseif ($saved_suburb !== '' && is_numeric($saved_suburb)) {
                // Fallback: at least show selected suburb label
                $suburb = $collivery->getSuburb((int)$saved_suburb);
                if (is_array($suburb) && isset($suburb['id'], $suburb['name'])) {
                    $allFields["{$type}_suburb"]['options'] = array_replace(
                        ['' => 'Select suburb'],
                        [(string)$saved_suburb => $suburb['name']],
                        (array)($allFields["{$type}_suburb"]['options'] ?? [])
                    );
                }
            }
        }
    }

    foreach ($keys as $key) {
        if (!isset($allFields[$key])) continue;

        $args = $allFields[$key];

        // Left column label (keep this one)
        $leftLabel = !empty($args['label'])
            ? $args['label']
            : ucwords(str_replace('_', ' ', str_replace($type . '_', '', $key)));

        // Prevent woocommerce_form_field() label inside <td>
        $args['label'] = '';
        $args['label_class'] = ['screen-reader-text'];

        // Mark these fields so our "hide city" logic won't hide OUR injected city dropdown
        $args['custom_attributes'] = array_merge((array)($args['custom_attributes'] ?? []), [
            'data-mds-collivery' => '1',
        ]);

        // Make selects smaller in admin
        $args['class'] = array_merge((array)($args['class'] ?? []), ['mds-admin-compact']);
        $args['input_class'] = array_merge((array)($args['input_class'] ?? []), ['mds-admin-compact-input']);

        $value = get_user_meta($user_id, $key, true);

        echo '<tr class="mds-collivery-row">';
        echo '<th><label for="' . esc_attr($key) . '">' . esc_html($leftLabel) . '</label></th>';
        echo '<td>';
        woocommerce_form_field($key, $args, $value);
        echo '</td>';
        echo '</tr>';
    }

    // In search mode, ensure hidden city/suburb exist so script.js detects search mode.
    if ($searchEnabled) {
        foreach (["{$type}_city", "{$type}_suburb"] as $hiddenKey) {
            if (!isset($allFields[$hiddenKey])) continue;

            $args = $allFields[$hiddenKey];
            $args['type'] = 'hidden';
            $args['label'] = '';
            $args['label_class'] = ['screen-reader-text'];
            $value = get_user_meta($user_id, $hiddenKey, true);

            echo '<tr class="mds-collivery-row mds-collivery-hidden-row" style="display:none;">';
            echo '<th></th><td>';
            woocommerce_form_field($hiddenKey, $args, $value);
            echo '</td></tr>';
        }
    }
}

/**
 * Inject into Woo billing/shipping tables right under Last name.
 * Hide Woo default "City" row ONLY (not our injected dropdown).
 * When search is OFF: trigger town change so suburbs list loads, then re-select saved suburb.
 */
add_action('admin_footer', function () {
    global $pagenow;
    if (!in_array($pagenow, ['profile.php', 'user-edit.php'], true)) return;
    ?>
    <style>
        /* Reduce widths for Collivery fields only */
        .mds-admin-compact-input,
        .mds-admin-compact select,
        select.mds-admin-compact-input {
            width: 350px !important;
            max-width: 350px !important;
        }

        .select2-container.mds-admin-compact-input,
        .select2-container--default .select2-selection--single {
            max-width: 350px !important;
        }
        .select2-container { min-width: 350px !important; }
    </style>

    <script>
    jQuery(function($){

        function hideWooCityRowsOnly() {
            // Hide ONLY Woo's own city rows: those do NOT have our data-mds-collivery attribute.
            // (Our injected town/city dropdown uses the same name/id, so we MUST exclude it.)
            $('tr').has('[name="billing_city"]:not([data-mds-collivery="1"])').hide();
            $('tr').has('[name="shipping_city"]:not([data-mds-collivery="1"])').hide();
        }

        function injectColliveryRows() {
            var $hidden = $('#mds-collivery-hidden-rows');
            if (!$hidden.length) return;

            var $billingAfter  = $('#billing_last_name').closest('tr');
            var $shippingAfter = $('#shipping_last_name').closest('tr');

            var $billingRows  = $hidden.find('.mds-block[data-target="billing"] .mds-collivery-row');
            var $shippingRows = $hidden.find('.mds-block[data-target="shipping"] .mds-collivery-row');

            if ($billingAfter.length && $billingRows.length)  $billingAfter.after($billingRows);
            if ($shippingAfter.length && $shippingRows.length) $shippingAfter.after($shippingRows);

            $hidden.remove();
        }

        function ensureNonSearchSuburbsLoaded(type) {
            // Only for search OFF mode
            if (!window.mds_profile_prefill || window.mds_profile_prefill.searchEnabled) return;

            var town   = (window.mds_profile_prefill[type] || {}).town || '';
            var suburb = (window.mds_profile_prefill[type] || {}).suburb || '';

            var $townSel   = $('[name="'+type+'_city"][data-mds-collivery="1"]');
            var $suburbSel = $('[name="'+type+'_suburb"][data-mds-collivery="1"]');

            if (!$townSel.length || !$suburbSel.length) return;

            // If town saved, make sure it's selected
            if (town !== '') {
                $townSel.val(town);
            }

            // Ensure the shared script allows updates on admin pages
            if (typeof window.isBuildingPage !== 'undefined') {
                window.isBuildingPage = false;
            }
            if (typeof window.overrideChange !== 'undefined') {
                window.overrideChange = true;
            }

            // Trigger whatever your script.js uses to fetch suburbs for a town
            // (most implementations listen to change on *city/town* select)
            $townSel.trigger('change');

            // After suburbs load, re-select saved suburb
            if (suburb !== '') {
                setTimeout(function(){
                    $suburbSel.val(suburb).trigger('change');
                }, 600);

                setTimeout(function(){
                    $suburbSel.val(suburb).trigger('change');
                }, 1400);
            }
        }

        // 1) Inject rows under last name
        injectColliveryRows();

        // 2) Hide Woo city row, but keep our injected town dropdown
        hideWooCityRowsOnly();
        setTimeout(hideWooCityRowsOnly, 300);
        setTimeout(hideWooCityRowsOnly, 900);

        // 3) If search is OFF: load suburb list + preselect saved suburb (both billing & shipping)
        ensureNonSearchSuburbsLoaded('billing');
        ensureNonSearchSuburbsLoaded('shipping');
        $(window).on('load', function(){
            ensureNonSearchSuburbsLoaded('billing');
            ensureNonSearchSuburbsLoaded('shipping');
        });

        // If Woo re-renders address parts after country/state changes, re-hide city rows
        $(document).on('change', '#billing_country,#billing_state,#shipping_country,#shipping_state', function(){
            setTimeout(hideWooCityRowsOnly, 50);
            setTimeout(hideWooCityRowsOnly, 300);
        });
    });
    </script>
    <?php
});

/**
 * Save ONLY Collivery fields.
 */
add_action('personal_options_update', 'mds_save_collivery_fields_profile', 20);
add_action('edit_user_profile_update', 'mds_save_collivery_fields_profile', 20);

function mds_save_collivery_fields_profile($user_id)
{
    if (!current_user_can('edit_user', $user_id)) return;

    $mds = MdsColliveryService::getInstance();
    if (!$mds || !$mds->isEnabled()) return;

    $searchEnabled = $mds->isTownsSuburbsSearchEnabled();

    foreach (['billing', 'shipping'] as $type) {
        $keys = ["{$type}_location_type"];

        if ($searchEnabled) {
            // town_city_search value is suburb id, keep both
            $keys[] = "{$type}_town_city_search";
            $keys[] = "{$type}_suburb";
        } else {
            // non-search mode: town (city dropdown) + suburb dropdown
            $keys[] = "{$type}_city";
            $keys[] = "{$type}_suburb";
        }

        foreach ($keys as $key) {
            if (isset($_POST[$key])) {
                update_user_meta($user_id, $key, sanitize_text_field(wp_unslash($_POST[$key])));
            }
        }
    }
}
