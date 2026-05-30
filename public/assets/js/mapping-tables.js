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
        },
        {
            connection: '[data-role="lookup-connection"]',
            table: '[data-role="lookup-table"]',
            filter: '[data-role="lookup-table-filter"]',
            status: '[data-role="lookup-table-status"]'
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
        select.appendChild(option('', 'Bitte wählen', currentValue === ''));

        if (currentValue && !tables.some(function (table) { return table.name === currentValue; })) {
            select.appendChild(option(currentValue, currentValue + ' (gespeichert)', true));
        }

        tables.forEach(function (table) {
            var name = table.name || '';
            var label = table.label || name || '';

            if (!name) {
                return;
            }

            if (normalizedFilter && label.toLowerCase().indexOf(normalizedFilter) === -1) {
                return;
            }
            select.appendChild(option(name, label, name === currentValue));
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

    function fillColumnSelect(select, columns, currentValue) {
        select.innerHTML = '';
        select.appendChild(option('', 'Bitte wählen', currentValue === ''));

        if (currentValue && !columns.some(function (column) { return column.name === currentValue; })) {
            select.appendChild(option(currentValue, currentValue + ' (gespeichert)', true));
        }

        columns.forEach(function (column) {
            var name = column.name || '';
            var label = column.label || name || '';

            if (!name) {
                return;
            }

            select.appendChild(option(name, label, name === currentValue));
        });
    }

    function setupLookupColumns() {
        var connection = document.querySelector('[data-role="lookup-connection"]');
        var table = document.querySelector('[data-role="lookup-table"]');
        var keyColumn = document.querySelector('[data-role="lookup-key-column"]');
        var valueColumn = document.querySelector('[data-role="lookup-value-column"]');
        var resultKeyColumn = document.querySelector('[data-role="lookup-result-key-column"]');

        if (!connection || !table || !keyColumn || !valueColumn) {
            return;
        }

        var currentKeyColumn = keyColumn.getAttribute('data-current') || keyColumn.value || '';
        var currentValueColumn = valueColumn.getAttribute('data-current') || valueColumn.value || '';
        var currentResultKeyColumn = resultKeyColumn ? resultKeyColumn.getAttribute('data-current') || resultKeyColumn.value || '' : '';

        function reload() {
            var connectionId = connection.value || '';
            var tableName = table.value || '';

            if (!connectionId || !tableName) {
                fillColumnSelect(keyColumn, [], currentKeyColumn);
                fillColumnSelect(valueColumn, [], currentValueColumn);
                if (resultKeyColumn) {
                    fillColumnSelect(resultKeyColumn, [], currentResultKeyColumn);
                }
                return;
            }

            keyColumn.disabled = true;
            valueColumn.disabled = true;
            if (resultKeyColumn) {
                resultKeyColumn.disabled = true;
            }

            fetch('/admin/api/connection-table-columns?connection_id=' + encodeURIComponent(connectionId) + '&table=' + encodeURIComponent(tableName), {
                headers: {'Accept': 'application/json'}
            })
                .then(function (response) { return response.json(); })
                .then(function (payload) {
                    if (!payload.success) {
                        throw new Error(payload.message || 'Spalten konnten nicht geladen werden.');
                    }

                    var columns = Array.isArray(payload.columns) ? payload.columns : [];
                    fillColumnSelect(keyColumn, columns, currentKeyColumn);
                    fillColumnSelect(valueColumn, columns, currentValueColumn);
                    if (resultKeyColumn) {
                        fillColumnSelect(resultKeyColumn, columns, currentResultKeyColumn);
                    }
                })
                .catch(function () {
                    fillColumnSelect(keyColumn, [], currentKeyColumn);
                    fillColumnSelect(valueColumn, [], currentValueColumn);
                    if (resultKeyColumn) {
                        fillColumnSelect(resultKeyColumn, [], currentResultKeyColumn);
                    }
                })
                .finally(function () {
                    keyColumn.disabled = false;
                    valueColumn.disabled = false;
                    if (resultKeyColumn) {
                        resultKeyColumn.disabled = false;
                    }
                });
        }

        connection.addEventListener('change', reload);
        table.addEventListener('change', reload);
        table.addEventListener('blur', reload);
        keyColumn.addEventListener('change', function () {
            currentKeyColumn = keyColumn.value;
            keyColumn.setAttribute('data-current', currentKeyColumn);
        });
        valueColumn.addEventListener('change', function () {
            currentValueColumn = valueColumn.value;
            valueColumn.setAttribute('data-current', currentValueColumn);
        });
        if (resultKeyColumn) {
            resultKeyColumn.addEventListener('change', function () {
                currentResultKeyColumn = resultKeyColumn.value;
                resultKeyColumn.setAttribute('data-current', currentResultKeyColumn);
            });
        }

        reload();
    }

    function setupEndpointMappingFilter() {
        var workspace = document.querySelector('select[name="workspace_id"]');
        var mapping = document.querySelector('[data-role="endpoint-mapping-select"]');

        if (!workspace || !mapping) {
            return;
        }

        function applyFilter() {
            var workspaceId = workspace.value || '';

            Array.prototype.forEach.call(mapping.options, function (option) {
                var optionWorkspaceId = option.getAttribute('data-workspace-id') || '';
                var visible = option.value === '' || workspaceId === '' || optionWorkspaceId === workspaceId;
                option.hidden = !visible;
                option.disabled = !visible;
            });

            if (mapping.selectedOptions.length > 0 && mapping.selectedOptions[0].disabled) {
                mapping.value = '';
            }
        }

        workspace.addEventListener('change', applyFilter);
        applyFilter();
    }

    pairs.forEach(setup);
    setupLookupColumns();
    setupEndpointMappingFilter();
})();
