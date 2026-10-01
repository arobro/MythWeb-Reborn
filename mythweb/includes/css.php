<?php
/**
 * Various routines that deal with figuring out and/or displaying css.
 *
 * @license     GPL
 *
 * @package     MythWeb
 *
 **/

/**
 * Returns a classname for a Program or a Schedule, based on its recording
 * settings.
 **/
    function category_class(&$item) {
        $css_class = array();

        // Disable categories colorization
        if (isset($_SESSION['genre_colors']) && $_SESSION['genre_colors'] == 0) {
            $css_class[] = 'cat_Unknown';
            return $css_class[0];
        }

        // Recording classes
        $css_class[] = recstatus_class($item);

        // Category type
        if (!empty($item->category_type) && !preg_match('/unknown/i', $item->category_type)) {
            $css_class[] = 'type_' . preg_replace("/[^a-zA-Z0-9\-_]+/", '_', $item->category_type);
        }

        // Extract ONLY the first word from the category using standard strtolower
        $raw_cat = isset($item->category) ? $item->category : '';
        $clean_cat = trim(strtolower($raw_cat));

        // Split by non-alphanumeric characters to reliably grab the first word
        $parts = preg_split('/[^a-z0-9]+/i', $clean_cat, -1, PREG_SPLIT_NO_EMPTY);
        $first_word = !empty($parts) ? $parts[0] : '';

        // Static cache for category matches
        static $cache = array();

        if (!empty($first_word) && isset($cache[$first_word])) {
            $css_class[] = $cache[$first_word];
        } else {
            global $Categories;
            $matched_class = null;

            if (!empty($Categories) && !empty($first_word)) {
                foreach ($Categories as $cat => $details) {
                    // Normalize target category name from $Categories
                    $target_cat = strtolower(isset($details[0]) ? $details[0] : '');

                    // Extract first word of the array category details
                    $target_parts = preg_split('/[^a-z0-9]+/i', $target_cat, -1, PREG_SPLIT_NO_EMPTY);
                    $target_word = !empty($target_parts) ? $target_parts[0] : '';

                    // Check direct match on the first word
                    if ($first_word === $target_word) {
                        $matched_class = 'cat_' . $cat;
                        break;
                    }

                    // Fallback: Check optional regex pattern if defined
                    if (!empty($details[1]) && @preg_match('/' . $details[1] . '/i', $first_word)) {
                        $matched_class = 'cat_' . $cat;
                        break;
                    }
                }
            }

            // Default if no match is found
            if (!$matched_class) {
                $matched_class = 'cat_Unknown';
            }

            // Cache the result for this first word
            if (!empty($first_word)) {
                $cache[$first_word] = $matched_class;
            }

            $css_class[] = $matched_class;
        }

        // Exception: If category contains "crime" and is NOT "Crime / Drama"
        if (stripos($raw_cat, 'crime') !== false && !preg_match('/^\s*crime\s*[\/\-]\s*drama\s*$/i', $raw_cat)) {
            $css_class[] = 'cat_Crime_True';
        }

        return preg_replace('/ +/', ' ', implode(' ', $css_class));
    }
/**
 * Returns a classname for a Program or a Schedule, based on its recording
 * status.
 **/
    function recstatus_class(&$item) {
        if ($item->recstatus && !strcasecmp(get_class($item), 'program')) {
            switch ($item->recstatus) {
                case 'ForceRecord':
                    return 'record_override_record';
                case 'Recording':
                case 'WillRecord':
                    return 'will_record';
                case 'Conflict':
                case 'Overlap':
                    return 'record_conflicting';
                case 'PreviousRecording':
                case 'CurrentRecording':
                    return 'record_duplicate';
                case 'Recorded':
                    return 'record_old_duplicate';
                case 'ManualOverride':
                case 'Cancelled':
                    return 'record_override_suppress';
                default:
                    return 'record_suppressed';
            }
        }
        return NULL;
    }

