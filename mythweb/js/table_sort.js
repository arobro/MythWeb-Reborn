/**
 * Javascript Table sorting lib
 *
 * @license LGPL
 */

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
        SortableTables.start();
    });
} else {
    SortableTables.start();
}

var SortableTables = {
    tables:                     {},
    callback_presort:           null,
    callback_postsort:          null,
    callback_preload:           null,
    callback_postload:          null,

    currently_sorting_table:    null,

    start:                      function () {
        var tables = document.querySelectorAll('table[sortable="true"]');
        for (var i = 0; i < tables.length; i++) {
            SortableTables.setupTable(tables[i]);
        }
    },

    setupTable:                 function (table) {
        if (this.callback_preload)
            this.callback_preload(table);
        // We require a unique id
        if (!table.id || (this.tables && this.tables[table.id]))
            return;
        SortableTables.tables[table.id] = new SortableTable(table);
        if (this.callback_postload)
            this.callback_postload(table);
    },

    sort:                       function (table_id, header_index) {
        // 25% speed boost on large tables (900 rows+) by not allowing multiple sorts at the same time.
        if (this.currently_sorting_table != null)
            return;
        if (this.callback_presort)
            this.callback_presort(table_id, header_index);
        if (this.tables[table_id] && this.tables[table_id].callback_presort)
            this.tables[table_id].callback_presort(table_id, header_index);

        if (this.tables[table_id]) {
            this.tables[table_id].sort(header_index);
            if (this.tables[table_id].callback_postsort)
                this.tables[table_id].callback_postsort(table_id, header_index);
        }

        if (this.callback_postsort)
            this.callback_postsort(table_id, header_index);
    }
};

function SortableTable(table) {
    // Class vars
    this.table                  = null;
    this.header                 = null;
    this.body                   = null;
    this.rows                   = null;

    this.cache_sort_functions   = [];
    this.cache_has_attribute    = [];
    this.cache_sorted_index     = null;
    this.cache_previous_sorts   = [];
    this.cache_sort_hash        = [];
    this.cache_sort_direction   = false;

    this.multisort              = false;
    this.zebra_stripe           = false;

    this.sort_cell_index        = null;
    this.sort_header_index      = null;

    this.callback_presort       = null;
    this.callback_postsort      = null;

    if (!table || !table.tBodies || !table.tBodies[0] || table.tBodies[0].rows.length < 2)
        return;

    this.table  = typeof table === 'string' ? document.getElementById(table) : table;
    this.header = this.table.tHead.rows[this.table.tHead.rows.length - 1];
    this.body   = this.table.tBodies[0];
    this.rows   = this.body.rows;

    this.multisort = this.table.getAttribute('multisort') === 'true';

    if (this.rows[0].className.match(/even/) || this.rows[0].className.match(/odd/))
        this.zebra_stripe = true;

    // Setup the header with links
    var cells           = this.header.cells;
    var cells_count     = cells.length;
    var cell_coloffset  = 0;

    for (var i = 0; i < cells_count; i++) {
        var cell                = cells[i];
        var cell_index          = i + cell_coloffset;
        var header_index        = i;
        this.cache_sort_hash[i] = cell_index;

        // Handle colspans...
        if (cell.hasAttribute('colspan')) {
            cell_coloffset += parseInt(cell.getAttribute('colspan'), 10) - 1;
        }

        if (cell.getAttribute('sortable') === 'false')
            continue;

        var self = this;
        (function (hIdx) {
            cell.onclick = function () {
                SortableTables.sort(self.table.id, hIdx);
                return false;
            };
        })(header_index);

        cell.classList.add('link');

        // Heat the cache...
        this.cache_has_attribute[cell_index]  = false;
        // Precache the sort function...
        this.cache_sort_functions[cell_index] = this.getSortFunction(header_index);
    }
}

SortableTable.prototype.getSortFunction = function (header_index) {
    var cell_index = this.cache_sort_hash[header_index];
    var sort_function = 'sortCaseInsensitive';

    this.cache_has_attribute[header_index] = this.rows[0].cells[cell_index].hasAttribute('sort_value');

    if (this.header.cells[header_index].hasAttribute('sort_hint')) {
        sort_function = this.header.cells[header_index].getAttribute('sort_hint');
    } else {
        var value = this.cache_has_attribute[header_index]
            ? this.rows[0].cells[cell_index].getAttribute('sort_value')
            : this.rows[0].cells[cell_index].textContent;

        if (value.match(/^\w+ \d+[,] \d\d\d\d/))
            sort_function = 'sortEnglishDate';
        else if (value.match(/^\w+[,] \w+ \d+[,] \d+[:]\d+ \w+$/))
            sort_function = 'sortLongEnglishDate';
        else if (value.match(/^\d+ days$/) || value === 'Today' || value === 'Tomorrow' || value === 'Yesterday')
            sort_function = 'sortExpiration';
        else if (value.match(/^\d\d[\/-]\d\d[\/-]\d\d\d\d$/) || value.match(/^\d\d[\/-]\d\d[\/-]\d\d$/))
            sort_function = 'sortDate';
        else if (value.match(/^\W+ \d+$/))
            sort_function = 'sortMonthDay';
        else if (value.match(/^[-]?[\d\.]+[%]*$/))
            sort_function = 'sortNumeric';
        else if (value.match(/^[£$]/))
            sort_function = 'sortCurrency';
    }

    return SortableTableSorts[sort_function] || SortableTableSorts.sortCaseInsensitive;
};

SortableTable.prototype.sort = function (header_index) {
    this.sort_header_index = header_index;
    this.sort_cell_index   = this.cache_sort_hash[header_index];

    var row_length = this.rows.length;
    var rows       = [];
    for (var i = 0; i < row_length; i++) {
        rows.push(this.rows[i]);
    }

    // Check to see if we're already sorted, and if so, just invert it
    var lastSorted = this.cache_previous_sorts[this.cache_previous_sorts.length - 1];
    if (lastSorted === this.sort_cell_index) {
        rows.reverse();
    } else {
        SortableTables.currently_sorting_table = this;
        var sort_function = this.doSort;
        rows.sort(sort_function);
        SortableTables.currently_sorting_table = null;
    }

    // Move back to the table...
    for (var j = 0; j < row_length; j++) {
        this.body.appendChild(rows[j]);
    }

    // Setup the arrows...
    this.doHeaderArrows();
    // Fix any zebra striping
    this.fixZebraStripes();

    // Remove the current index from previous sorts and keep unique values
    var newPrevious = [];
    for (var k = 0; k < this.cache_previous_sorts.length; k++) {
        var val = this.cache_previous_sorts[k];
        if (val !== this.sort_cell_index && newPrevious.indexOf(val) === -1) {
            newPrevious.push(val);
        }
    }
    this.cache_previous_sorts = newPrevious;
    this.cache_previous_sorts.push(this.sort_cell_index);
};

SortableTable.prototype.doSort = function (a, b, cell_index) {
    var tableObj = SortableTables.currently_sorting_table;

    if (!cell_index && cell_index !== 0)
        cell_index = tableObj.sort_cell_index;

    var value_a, value_b;

    if (tableObj.cache_has_attribute[cell_index]) {
        value_a = a.cells[cell_index].getAttribute('sort_value');
        value_b = b.cells[cell_index].getAttribute('sort_value');
    } else {
        value_a = a.cells[cell_index].textContent;
        value_b = b.cells[cell_index].textContent;
    }

    var comparison = 0;
    if (value_a !== value_b) {
        comparison = tableObj.cache_sort_functions[cell_index](value_a, value_b);
    }

    if (tableObj.multisort && comparison === 0 && tableObj.cache_previous_sorts.length > 0) {
        var multisort_index = tableObj.cache_previous_sorts.pop();
        comparison          = tableObj.doSort(a, b, multisort_index);
        tableObj.cache_previous_sorts.push(multisort_index);
    }
    return comparison;
};

SortableTable.prototype.doHeaderArrows = function () {
    var spans = this.table.querySelectorAll('thead span.sortArrow');
    for (var i = 0; i < spans.length; i++) {
        spans[i].innerHTML = '&nbsp;&nbsp;&nbsp;';
    }

    var currentCell = this.header.cells[this.sort_header_index];
    var arrow_span  = currentCell.querySelector('span.sortArrow');

    if (!arrow_span) {
        arrow_span = document.createElement('span');
        arrow_span.className = 'sortArrow';
        currentCell.appendChild(arrow_span);
    }

    var lastSorted = this.cache_previous_sorts[this.cache_previous_sorts.length - 1];
    if (lastSorted === this.sort_cell_index && this.cache_sort_direction) {
        arrow_span.innerHTML = '&nbsp;&nbsp;&uarr;';
        this.cache_sort_direction = false;
    } else {
        arrow_span.innerHTML = '&nbsp;&nbsp;&darr;';
        this.cache_sort_direction = true;
    }
};

SortableTable.prototype.fixZebraStripes = function () {
    if (!this.zebra_stripe)
        return;

    var length = this.rows.length;
    var even   = false;
    for (var i = 0; i < length; i++) {
        var row = this.rows[i];
        if (even) {
            row.className = row.className.replace(/\bodd\b/g, 'even');
        } else {
            row.className = row.className.replace(/\beven\b/g, 'odd');
        }
        even = !even;
    }
};

var SortableTableSorts = {
    sortDate: function (a, b) {
        var dt1, dt2, yr;
        if (a.length === 10) {
            dt1 = a.substr(6, 4) + a.substr(3, 2) + a.substr(0, 2);
        } else {
            yr = a.substr(6, 2);
            yr = parseInt(yr, 10) < 50 ? '20' + yr : '19' + yr;
            dt1 = yr + a.substr(3, 2) + a.substr(0, 2);
        }

        if (b.length === 10) {
            dt2 = b.substr(6, 4) + b.substr(3, 2) + b.substr(0, 2);
        } else {
            yr = b.substr(6, 2);
            yr = parseInt(yr, 10) < 50 ? '20' + yr : '19' + yr;
            dt2 = yr + b.substr(3, 2) + b.substr(0, 2);
        }

        if (dt1 === dt2) return 0;
        return dt1 < dt2 ? -1 : 1;
    },

    sortDateUs: function (a, b) {
        var dt1, dt2, yr;
        if (a.length === 10) {
            dt1 = a.substr(6, 4) + a.substr(0, 2) + a.substr(3, 2);
        } else {
            yr = a.substr(6, 2);
            yr = parseInt(yr, 10) < 50 ? '20' + yr : '19' + yr;
            dt1 = yr + a.substr(0, 2) + a.substr(3, 2);
        }

        if (b.length === 10) {
            dt2 = b.substr(6, 4) + b.substr(0, 2) + b.substr(3, 2);
        } else {
            yr = b.substr(6, 2);
            yr = parseInt(yr, 10) < 50 ? '20' + yr : '19' + yr;
            dt2 = yr + b.substr(0, 2) + b.substr(3, 2);
        }

        if (dt1 === dt2) return 0;
        return dt1 < dt2 ? -1 : 1;
    },

    sortEnglishDate: function (a, b) {
        if (a === 'none' || a.length === 0) return -1;
        if (b === 'none' || b.length === 0) return 1;

        var aa_mon  = SortableTableSorts.englishMonthToNumber(a.substr(0, 3));
        var aa      = a.substr(4);
        var aa_year = parseFloat(aa.substr(aa.length - 4));
        var aa_day  = parseFloat(aa.substr(0, aa.length - 6));

        var bb_mon  = SortableTableSorts.englishMonthToNumber(b.substr(0, 3));
        var bb      = b.substr(4);
        var bb_year = parseFloat(bb.substr(bb.length - 4));
        var bb_day  = parseFloat(bb.substr(0, bb.length - 6));

        if (!aa_year || !aa_mon || !aa_day || !bb_year || !bb_mon || !bb_day)
            return SortableTableSorts.sortCaseSensitive(a, b);

        if (aa_year !== bb_year) return aa_year < bb_year ? -1 : 1;
        if (aa_mon !== bb_mon)   return aa_mon < bb_mon ? -1 : 1;
        if (aa_day !== bb_day)   return aa_day < bb_day ? -1 : 1;
        return 0;
    },

    sortEnglishDateInvert: function (a, b) {
        return SortableTableSorts.sortEnglishDate(b, a);
    },

    sortMonthDay: function (a, b) {
        if (a.length === 0) return -1;
        if (b.length === 0) return 1;

        var a_mon = SortableTableSorts.englishMonthToNumber(a.substr(0, 3));
        var a_day = parseFloat(a.substr(4));
        var b_mon = SortableTableSorts.englishMonthToNumber(b.substr(0, 3));
        var b_day = parseFloat(b.substr(4));

        if (a_mon !== b_mon) return a_mon < b_mon ? -1 : 1;
        if (a_day !== b_day) return a_day < b_day ? -1 : 1;
        return 0;
    },

    sortLongEnglishDate: function (a, b) {
        var aa = a.substr(5).split(/,/);
        var bb = b.substr(5).split(/,/);

        var aatmp = aa[1].split(/:/);
        var aa_hour = parseInt(aatmp[0], 10);
        var aa_min  = parseInt(aatmp[1].substr(0, 2), 10);
        if (aatmp[1].substr(3).toLowerCase() === 'pm' && aa_hour < 12) aa_hour += 12;

        var bbtmp = bb[1].split(/:/);
        var bb_hour = parseInt(bbtmp[0], 10);
        var bb_min  = parseInt(bbtmp[1].substr(0, 2), 10);
        if (bbtmp[1].substr(3).toLowerCase() === 'pm' && bb_hour < 12) bb_hour += 12;

        aa = aa[0];
        bb = bb[0];

        if (aa === bb) return 0;
        if (aa === 'none' || aa.length === 0) return -1;
        if (bb === 'none' || bb.length === 0) return 1;

        var aa_mon = SortableTableSorts.englishMonthToNumber(aa.substr(0, 3));
        var aa_day = parseFloat(aa.substr(4));
        var bb_mon = SortableTableSorts.englishMonthToNumber(bb.substr(0, 3));
        var bb_day = parseFloat(bb.substr(4));

        if (!aa_mon || !aa_day || !bb_mon || !bb_day) return -1;

        if (aa_mon !== bb_mon)   return aa_mon < bb_mon ? -1 : 1;
        if (aa_day !== bb_day)   return aa_day < bb_day ? -1 : 1;
        if (aa_hour !== bb_hour) return aa_hour < bb_hour ? -1 : 1;
        if (aa_min !== bb_min)   return aa_min < bb_min ? -1 : 1;
        return 0;
    },

    sortExpiration: function (a, b) {
        a = a.split(/ /)[0];
        b = b.split(/ /)[0];

        switch (a) {
            case 'Yesterday': a = -1; break;
            case 'Today':     a =  0; break;
            case 'Tomorrow':  a =  1; break;
            default:          a = parseFloat(a);
        }
        switch (b) {
            case 'Yesterday': b = -1; break;
            case 'Today':     b =  0; break;
            case 'Tomorrow':  b =  1; break;
            default:          b = parseFloat(b);
        }

        if (a === b) return 0;
        return a > b ? 1 : -1;
    },

    englishMonthToNumber: function (mon) {
        switch (mon.toLowerCase()) {
            case 'jan': return 1;
            case 'feb': return 2;
            case 'mar': return 3;
            case 'apr': return 4;
            case 'may': return 5;
            case 'jun': return 6;
            case 'jul': return 7;
            case 'aug': return 8;
            case 'sep': return 9;
            case 'oct': return 10;
            case 'nov': return 11;
            case 'dec': return 12;
        }
        return 0;
    },

    sortCurrency: function (a, b) {
        return parseFloat(a.replace(/[^0-9.]/g, '')) - parseFloat(b.replace(/[^0-9.]/g, ''));
    },

    sortNumeric: function (a, b) {
        b = parseFloat(b);
        if (isNaN(b)) return 1;
        a = parseFloat(a);
        if (isNaN(a)) return -1;
        return a - b;
    },

    sortNumericInvert: function (a, b) {
        return SortableTableSorts.sortNumeric(b, a);
    },

    sortCaseInsensitive: function (a, b) {
        a = a.toLowerCase();
        b = b.toLowerCase();
        if (a === b) return 0;
        return a > b ? 1 : -1;
    },

    sortCaseSensitive: function (a, b) {
        if (a === b) return 0;
        return a > b ? 1 : -1;
    },

    sortMythwebPlayTime: function (a, b) {
        if (a === b) return 0;

        var a_hrs = a.match(/([0-9]*) hr/);
        var a_min = a.match(/([0-9]*) min/);
        var a_val = (a_hrs ? a_hrs[1] * 60 : 0) + (a_min ? a_min[1] * 1 : 0);

        var b_hrs = b.match(/([0-9]*) hr/);
        var b_min = b.match(/([0-9]*) min/);
        var b_val = (b_hrs ? b_hrs[1] * 60 : 0) + (b_min ? b_min[1] * 1 : 0);

        return SortableTableSorts.sortNumeric(a_val, b_val);
    },

    sortMythwebChannel: function (a, b) {
        if (a === b) return 0;

        var a_channel = a.match(/([0-9]*) -/);
        a_channel = a_channel ? a_channel[1] : 0;

        var b_channel = b.match(/([0-9]*) -/);
        b_channel = b_channel ? b_channel[1] : 0;

        return SortableTableSorts.sortNumeric(a_channel, b_channel);
    },

    // Compatibility wrappers
    date_us: function (a, b) {
        return SortableTableSorts.sortDateUs(a, b);
    },

    numeric: function (a, b) {
        return SortableTableSorts.sortNumeric(a, b);
    },

    numeric_invert: function (a, b) {
        return SortableTableSorts.sortNumericInvert(a, b);
    }
};