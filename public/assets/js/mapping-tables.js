(function () {
    var pairs = [
        {
            connection: '[data-role="source-connection"]',
            table: '[data-role="source-table"]',
            filter: '[data-role="source-table-filter"]',
            status: '[data-role="source-table-status"]'
        },
        {
            connection: '[data-role="target-connection"]',
            table: '[data-role="target-table"]',
            filter: '[data-role="target-table-filter"]',
            status: '[data-role="target-table-status"]'
        }
    ];

    function option(value, label, selected) {
        var item = document.createElement('option');
        item.value = value;
        item.textContent = label;
        item.selected = selected;
        return item;
    }

    function setStatus(element, message) {
        if (element) {
            element.textContent = message || '';
        }
    }

    function fillSelect(select, tables, currentValue, filterText) {
        var normalizedFilter = (filterText || '').toLowerCase();
        select.innerHTML = '';
        select.appendChild(option('', 'Bitte waehlen', currentValue === ''));

        if (currentValue && !tables.some(function (table) { return table.name === currentValue; })) {
            select.appendChild(option(currentValue, currentValue + ' (gespeichert)', true));
        }

        tables.forEach(function (table) {
            if (normalizedFilter && table.label.toLowerCase().indexOf(normalizedFilter) === -1) {
                return;
            }
            select.appendChild(option(table.name, table.label, table.name === currentValue));
        });
    }

    function setup(pair) {
        var connection = document.querySelector(pair.connection);
        var table = document.querySelector(pair.table);
        var filter = document.querySelector(pair.filter);
        var status = document.querySelector(pair.status);

        if (!connection || !table) {
            return;
        }

        var tables = [];
        var currentValue = table.getAttribute('data-current') || table.value || '';

        function reload(resetValue) {
            var connectionId = connection.value;
            if (resetValue) {
                currentValue = '';
                table.setAttribute('data-current', '');
            }

            if (!connectionId) {
                fillSelect(table, [], currentValue, filter ? filter.value : '');
                setStatus(status, '');
                return;
            }

            table.disabled = true;
            setStatus(status, 'Tabellen werden geladen...');

            fetch('/admin/api/connection-tables?connection_id=' + encodeURIComponent(connectionId), {
                headers: {'Accept': 'application/json'}
            })
                .then(function (response) { return response.json(); })
                .then(function (payload) {
                    if (!payload.success) {
                        throw new Error(payload.message || 'Tabellen konnten nicht geladen werden.');
                    }
                    tables = Array.isArray(payload.tables) ? payload.tables : [];
                    fillSelect(table, tables, currentValue, filter ? filter.value : '');
                    setStatus(status, tables.length + ' Tabellen geladen.');
                })
                .catch(function () {
                    tables = [];
                    fillSelect(table, [], currentValue, '');
                    setStatus(status, 'Tabellen konnten nicht geladen werden. Gespeicherter Wert bleibt erhalten.');
                })
                .finally(function () {
                    table.disabled = false;
                });
        }

        connection.addEventListener('change', function () {
            reload(true);
        });

        table.addEventListener('change', function () {
            currentValue = table.value;
            table.setAttribute('data-current', currentValue);
        });

        if (filter) {
            filter.addEventListener('input', function () {
                fillSelect(table, tables, currentValue, filter.value);
            });
        }

        reload(false);
    }

    pairs.forEach(setup);
})();
