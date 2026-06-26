(function ($) {
    'use strict';

    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function badgeClassForType(type) {
        var normalized = String(type || '').toLowerCase();
        if (normalized === 'supplier') {
            return 'bg-warning text-dark';
        }
        if (normalized === 'customer') {
            return 'bg-info text-dark';
        }
        return 'bg-secondary';
    }

    function renderTypeBadges(row) {
        var labels = [];
        var types = [];

        if (Array.isArray(row && row.entity_types) && row.entity_types.length > 0) {
            types = row.entity_types.map(function (item) { return String(item || '').toLowerCase(); });
        } else if (row && row.entity_type) {
            types = [String(row.entity_type || '').toLowerCase()];
        }

        if (row && row.entity_type_label) {
            labels = String(row.entity_type_label).split('،').map(function (item) {
                return String(item || '').trim();
            }).filter(function (item) { return item !== ''; });
        }

        if (types.length === 0 && labels.length === 0) {
            return '';
        }

        var output = [];
        var size = Math.max(types.length, labels.length);
        for (var i = 0; i < size; i += 1) {
            var label = labels[i] || labels[0] || types[i] || '';
            var type = types[i] || types[0] || '';
            output.push('<span class="badge ' + badgeClassForType(type) + '">' + escapeHtml(label) + '</span>');
        }
        return '<div class="d-flex flex-wrap gap-1 mt-1">' + output.join('') + '</div>';
    }

    function initCardPicker($picker) {
        if ($picker.data('pickerBound')) {
            return;
        }
        $picker.data('pickerBound', true);

        var url = String($picker.data('search-url') || '');
        if (!url) {
            return;
        }
        var placeholder = String($picker.data('placeholder') || '');
        var $input = $picker.find('[data-search-input]').first();
        var $hidden = $picker.find('input[type="hidden"][name]').first();
        var $results = $picker.find('[data-search-results]').first();
        var $selectedBox = $picker.find('[data-selected-box]').first();
        var $selectedText = $picker.find('[data-selected-text]').first();
        var $selectedId = $picker.find('[data-selected-id]').first();
        var $clear = $picker.find('[data-clear-selection]').first();
        var initialId = String($picker.data('initial-id') || '');
        var initialText = String($picker.data('initial-text') || '');
        var debounceTimer = null;

        function hideResults() {
            $results.addClass('d-none').empty();
        }

        function setSelected(id, text, triggerChange) {
            var selectedIdValue = String(id || '').trim();
            var selectedTextValue = String(text || '').trim();
            var previous = String($hidden.val() || '').trim();
            $hidden.val(selectedIdValue);
            if (selectedIdValue === '') {
                $selectedBox.addClass('d-none');
                $selectedText.text('');
                $selectedId.text('');
                return;
            }
            $selectedBox.removeClass('d-none');
            $selectedText.text(selectedTextValue !== '' ? selectedTextValue : ('#' + selectedIdValue));
            $selectedId.text('#' + selectedIdValue);
            if (triggerChange && previous !== selectedIdValue) {
                $hidden.trigger('change');
            }
        }

        function renderResults(rows) {
            if (!Array.isArray(rows) || rows.length === 0) {
                $results.removeClass('d-none').html('<div class="list-group-item text-muted text-center">نتیجه‌ای یافت نشد.</div>');
                return;
            }
            var html = rows.map(function (row) {
                var id = String((row && row.id) || '');
                var text = String((row && row.text) || row.name || ('#' + id));
                var badges = renderTypeBadges(row);
                return '<button type="button" class="list-group-item list-group-item-action text-start" data-result-id="'
                    + id.replace(/"/g, '&quot;')
                    + '" data-result-text="'
                    + text.replace(/"/g, '&quot;')
                    + '"><i class="ph-user me-2 text-primary"></i>'
                    + '<span>' + escapeHtml(text) + '</span>'
                    + badges
                    + '</button>';
            }).join('');
            $results.removeClass('d-none').html(html);
        }

        function doSearch(q) {
            $.ajax({
                url: url,
                method: 'GET',
                dataType: 'json',
                data: { q: q, limit: 30 }
            }).done(function (res) {
                renderResults((res && res.results) ? res.results : []);
            }).fail(function () {
                $results.removeClass('d-none').html('<div class="list-group-item text-danger text-center">خطا در جستجو</div>');
            });
        }

        $input.attr('placeholder', placeholder);
        $input.on('input', function () {
            var q = String($input.val() || '').trim();
            if (debounceTimer) {
                clearTimeout(debounceTimer);
            }
            if (q.length < 1) {
                hideResults();
                return;
            }
            debounceTimer = setTimeout(function () {
                doSearch(q);
            }, 260);
        });

        $results.on('click', '[data-result-id]', function () {
            var $btn = $(this);
            setSelected($btn.data('result-id'), $btn.data('result-text'));
            $input.val('');
            hideResults();
        });

        $clear.on('click', function () {
            setSelected('', '');
            $input.val('').trigger('focus');
        });

        $picker.on('accounting:card-picker:set', function (_event, payload) {
            payload = payload && typeof payload === 'object' ? payload : {};
            setSelected(payload.id || '', payload.text || '', true);
            $input.val('');
            hideResults();
        });

        $(document).on('click.entityCardPicker', function (e) {
            if (!$.contains($picker.get(0), e.target)) {
                hideResults();
            }
        });

        setSelected(initialId, initialText);
    }

    function boot() {
        $('.js-accounting-card-picker').each(function () {
            initCardPicker($(this));
        });
    }

    $(boot);
})(window.jQuery);

