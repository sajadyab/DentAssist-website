// Tooth Chart Module with SVG support.
(function() {
    'use strict';

    const statusClasses = ['healthy', 'cavity', 'filled', 'crown', 'root-canal', 'missing', 'implant', 'bridge'];
    const chartConfigs = {
        adult: {
            asset: '../assets/images/teeth-chart.svg',
            label: 'Adult teeth chart',
            teeth: Array.from({ length: 32 }, (_, index) => index + 1)
        },
        primary: {
            asset: '../assets/images/tooth-chart-primary.svg',
            label: 'Baby teeth chart',
            teeth: Array.from({ length: 20 }, (_, index) => index + 1)
        }
    };

    let patientId = null;
    let toothData = {};
    let readOnly = false;
    let chartType = 'adult';

    function normalizeInitArgs(isReadOnly, options) {
        if (typeof isReadOnly === 'object' && isReadOnly !== null) {
            options = isReadOnly;
            isReadOnly = !!options.readOnly;
        }

        return {
            readOnly: !!isReadOnly,
            chartType: options && options.chartType === 'primary' ? 'primary' : 'adult'
        };
    }

    function init(pid, isReadOnly, options) {
        const normalized = normalizeInitArgs(isReadOnly, options);
        patientId = pid;
        readOnly = normalized.readOnly;
        chartType = normalized.chartType;
        loadToothData();
    }

    function getConfig() {
        return chartConfigs[chartType] || chartConfigs.adult;
    }

    function loadToothData() {
        fetch(`../api/get_tooth_chart.php?patient_id=${patientId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    toothData = data.data || {};
                    renderChart();
                }
            })
            .catch(console.error);
    }

    function renderChart() {
        const container = document.getElementById('tooth-chart-container');
        if (!container) return;

        const config = getConfig();
        fetch(config.asset)
            .then(response => response.text())
            .then(svgText => {
                container.innerHTML = svgText;
                sizeChartSvg();
                ensureClickableAdultZones();
                applyToothData();
                attachEventListeners();
                addChartLabel();
                addLegend();
            })
            .catch(console.error);
    }

    function sizeChartSvg() {
        const svg = document.querySelector('#tooth-chart-container svg');
        if (!svg) return;

        const container = document.getElementById('tooth-chart-container');
        if (container) {
            container.style.width = '100%';
            container.style.overflowX = 'auto';
            container.style.padding = '8px 0 14px';
        }

        svg.removeAttribute('width');
        svg.removeAttribute('height');
        svg.style.width = '100%';
        svg.style.maxWidth = chartType === 'adult' ? '1120px' : '1160px';
        svg.style.height = 'auto';
        svg.style.display = 'block';
        svg.style.margin = '0 auto';
    }

    function ensureClickableAdultZones() {
        if (chartType !== 'adult' || document.getElementById('tooth-1')) return;

        const svg = document.querySelector('#tooth-chart-container svg');
        if (!svg) return;

        if (!svg.getAttribute('viewBox')) {
            const width = parseFloat(svg.getAttribute('width')) || 267;
            const height = parseFloat(svg.getAttribute('height')) || 164;
            svg.setAttribute('viewBox', `0 0 ${width} ${height}`);
        }

        const ns = 'http://www.w3.org/2000/svg';
        const overlay = document.createElementNS(ns, 'g');
        overlay.setAttribute('id', 'adult-tooth-click-zones');

        const upperCenters = [
            [18, 85], [36, 85], [56, 85], [72, 85],
            [85, 85], [98, 85], [111, 85], [124, 85],
            [144, 85], [156, 85], [167, 85], [181, 85],
            [196, 85], [214, 85], [230, 85], [248, 85]
        ];
        const lowerCenters = [
            [18, 140], [36, 140], [56, 140], [72, 140],
            [85, 140], [98, 140], [111, 140], [124, 140],
            [144, 140], [156, 140], [167, 140], [181, 140],
            [196, 140], [214, 140], [230, 140], [248, 140]
        ];

        const CM_IN_PX = 37.8;

        for (let i = 1; i <= 32; i++) {
            const isUpper = i <= 16;
            const idx = isUpper ? i - 1 : i - 17;
            const center = isUpper ? upperCenters[idx] : lowerCenters[idx];
            const group = document.createElementNS(ns, 'g');
            group.setAttribute('id', `tooth-${i}`);
            group.setAttribute('class', 'tooth-click-zone');
            group.setAttribute('transform', `translate(${center[0]} ${center[1] - CM_IN_PX})`);

            const hitCircle = document.createElementNS(ns, 'circle');
            hitCircle.setAttribute('r', '7.5');
            hitCircle.setAttribute('cx', '0');
            hitCircle.setAttribute('cy', '0');
            hitCircle.setAttribute('fill', 'transparent');
            hitCircle.setAttribute('stroke', 'none');

            const circle = document.createElementNS(ns, 'circle');
            circle.setAttribute('class', 'tooth');
            circle.setAttribute('r', '4.6');
            circle.setAttribute('cx', '0');
            circle.setAttribute('cy', '0');
            circle.setAttribute('fill-opacity', '0.78');
            circle.setAttribute('stroke', '#111');
            circle.setAttribute('stroke-width', '0.75');

            const label = document.createElementNS(ns, 'text');
            label.setAttribute('x', '0');
            label.setAttribute('y', '1');
            label.setAttribute('text-anchor', 'middle');
            label.setAttribute('dominant-baseline', 'middle');
            label.setAttribute('font-size', '3.6');
            label.setAttribute('font-weight', '700');
            label.setAttribute('fill', '#fff');
            label.setAttribute('stroke', '#111');
            label.setAttribute('stroke-width', '0.28');
            label.setAttribute('paint-order', 'stroke');
            label.setAttribute('pointer-events', 'none');
            label.textContent = String(i);

            group.appendChild(hitCircle);
            group.appendChild(circle);
            group.appendChild(label);
            overlay.appendChild(group);
        }

        svg.appendChild(overlay);
    }

    function getToothUseElement(toothNumber) {
        const toothGroup = document.getElementById(`tooth-${toothNumber}`);
        return toothGroup ? toothGroup.querySelector('use, .tooth') : null;
    }

    function applyToothData() {
        getConfig().teeth.forEach(toothNumber => {
            const toothElement = getToothUseElement(toothNumber);
            if (!toothElement) return;

            const data = toothData[toothNumber] || {};
            const status = data.status || 'healthy';

            toothElement.classList.remove(...statusClasses, 'has-notes');
            toothElement.classList.add(status);

            if (data.notes && data.notes.trim() !== '') {
                toothElement.classList.add('has-notes');
            }
        });
    }

    function attachEventListeners() {
        getConfig().teeth.forEach(toothNumber => {
            const toothGroup = document.getElementById(`tooth-${toothNumber}`);
            if (toothGroup) {
                toothGroup.addEventListener('click', (e) => {
                    e.preventDefault();
                    openToothModal(toothNumber);
                });
            }
        });
    }

    function addChartLabel() {
        const container = document.getElementById('tooth-chart-container');
        const label = document.createElement('div');
        label.className = 'tooth-chart-type-label';
        label.style.marginTop = '12px';
        label.style.fontSize = '14px';
        label.style.fontWeight = '700';
        label.style.color = '#495057';
        label.textContent = getConfig().label;
        container.appendChild(label);
    }

    function addLegend() {
        const container = document.getElementById('tooth-chart-container');
        const oldLegend = container.querySelector('.tooth-legend');
        if (oldLegend) oldLegend.remove();

        const legend = document.createElement('div');
        legend.className = 'tooth-legend';
        legend.style.display = 'flex';
        legend.style.flexWrap = 'wrap';
        legend.style.gap = '12px';
        legend.style.marginTop = '20px';
        legend.style.padding = '10px';
        legend.style.backgroundColor = '#fff';
        legend.style.borderRadius = '8px';
        legend.style.border = '1px solid #dee2e6';

        const statuses = [
            { label: 'Healthy', color: '#28a745' },
            { label: 'Cavity', color: '#fd7e14' },
            { label: 'Filled', color: '#007bff' },
            { label: 'Crown', color: '#6f42c1' },
            { label: 'Root Canal', color: '#ffc107' },
            { label: 'Missing', color: '#6c757d' },
            { label: 'Implant', color: '#20c997' },
            { label: 'Bridge', color: '#795548' }
        ];

        statuses.forEach(s => {
            const item = document.createElement('div');
            item.className = 'legend-item';
            item.style.display = 'flex';
            item.style.alignItems = 'center';
            item.style.gap = '6px';
            item.innerHTML = `
                <div style="width: 20px; height: 20px; background-color: ${s.color}; border-radius: 4px;"></div>
                <span style="font-size: 14px;">${s.label}</span>
            `;
            legend.appendChild(item);
        });

        container.appendChild(legend);
    }

    function setText(id, value, fallback) {
        const el = document.getElementById(id);
        if (el) el.textContent = value || fallback || '';
    }

    function setValue(id, value) {
        const el = document.getElementById(id);
        if (el) el.value = value || '';
    }

    function openToothModal(num) {
        const data = toothData[num] || {};
        setText('modal-tooth-number', num);

        if (readOnly) {
            setText('tooth-status-display', data.status || 'healthy');
            setText('tooth-diagnosis-display', data.diagnosis, 'No diagnosis recorded');
            setText('tooth-treatment-display', data.treatment, 'No treatment recorded');
            setText('tooth-notes-display', data.notes, 'No notes available');
            setText('tooth-updated-display', data.last_updated || data.updated_at, '-');
        } else {
            setValue('tooth-number-input', num);
            setValue('tooth-status', data.status || 'healthy');
            setValue('tooth-diagnosis', data.diagnosis || '');
            setValue('tooth-treatment', data.treatment || '');
            setValue('tooth-notes', data.notes || '');
        }

        const modalEl = document.getElementById('toothModal');
        const modal = new bootstrap.Modal(modalEl);
        modal.show();
    }

    window.toothChart = {
        init: init,
        saveTooth: function() {
            if (readOnly) return;

            const data = {
                patient_id: patientId,
                tooth_number: parseInt(document.getElementById('tooth-number-input').value, 10),
                status: document.getElementById('tooth-status').value,
                diagnosis: document.getElementById('tooth-diagnosis').value,
                treatment: document.getElementById('tooth-treatment').value,
                notes: document.getElementById('tooth-notes').value
            };

            fetch('../api/update_tooth.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            })
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    bootstrap.Modal.getInstance(document.getElementById('toothModal')).hide();
                    loadToothData();
                } else {
                    alert(res.message || 'Error saving tooth');
                }
            })
            .catch(err => {
                console.error(err);
                alert('Error saving tooth');
            });
        },
        deleteTooth: function() {
            if (readOnly) return;
            document.getElementById('tooth-status').value = 'missing';
            this.saveTooth();
        }
    };
})();
