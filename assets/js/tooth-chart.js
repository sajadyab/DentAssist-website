// Tooth Chart Module with SVG (fixed status colors)
(function() {
    'use strict';

    const statusColors = {
        'healthy': '#28a745',
        'cavity': '#fd7e14',
        'filled': '#007bff',
        'crown': '#6f42c1',
        'root-canal': '#ffc107',
        'missing': '#6c757d',
        'implant': '#20c997',
        'bridge': '#795548'
    };

    let patientId = null;
    let toothData = {};

    function init(pid) {
        patientId = pid;
        loadToothData();
    }

    function loadToothData() {
        fetch(`../api/get_tooth_chart.php?patient_id=${patientId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    toothData = data.data;
                    renderChart();
                }
            })
            .catch(console.error);
    }

    function renderChart() {
        const container = document.getElementById('tooth-chart-container');
        if (!container) return;

        fetch('../assets/images/tooth-chart.svg')
            .then(response => response.text())
            .then(svgText => {
                container.innerHTML = svgText;
                applyToothData();
                attachEventListeners();
                addLegend();
            })
            .catch(console.error);
    }

    function applyToothData() {
        for (let i = 1; i <= 32; i++) {
            const toothGroup = document.getElementById(`tooth-${i}`);
            if (!toothGroup) continue;

            // Find the <use> element inside the group
            const useElement = toothGroup.querySelector('use');
            if (!useElement) continue;

            const data = toothData[i] || {};
            const status = data.status || 'healthy';

            // Remove any existing status classes
            const statusClasses = ['healthy', 'cavity', 'filled', 'crown', 'root-canal', 'missing', 'implant', 'bridge'];
            useElement.classList.remove(...statusClasses);

            // Add the current status class
            useElement.classList.add(status);

            // Handle notes indicator
            if (data.notes && data.notes.trim() !== '') {
                useElement.classList.add('has-notes');
            } else {
                useElement.classList.remove('has-notes');
            }
        }
    }

    function attachEventListeners() {
        for (let i = 1; i <= 32; i++) {
            const toothGroup = document.getElementById(`tooth-${i}`);
            if (toothGroup) {
                toothGroup.addEventListener('click', (e) => {
                    e.preventDefault();
                    const toothNum = parseInt(toothGroup.getAttribute('id').split('-')[1]);
                    openToothModal(toothNum);
                });
            }
        }
    }

    function addLegend() {
        const container = document.getElementById('tooth-chart-container');
        // Remove existing legend if any
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
            { status: 'healthy', label: 'Healthy', color: '#28a745' },
            { status: 'cavity', label: 'Cavity', color: '#fd7e14' },
            { status: 'filled', label: 'Filled', color: '#007bff' },
            { status: 'crown', label: 'Crown', color: '#6f42c1' },
            { status: 'root-canal', label: 'Root Canal', color: '#ffc107' },
            { status: 'missing', label: 'Missing', color: '#6c757d' },
            { status: 'implant', label: 'Implant', color: '#20c997' },
            { status: 'bridge', label: 'Bridge', color: '#795548' }
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

    function openToothModal(num) {
        const data = toothData[num] || {};
        document.getElementById('modal-tooth-number').textContent = num;
        document.getElementById('tooth-number-input').value = num;
        document.getElementById('tooth-status').value = data.status || 'healthy';
        document.getElementById('tooth-diagnosis').value = data.diagnosis || '';
        document.getElementById('tooth-treatment').value = data.treatment || '';
        document.getElementById('tooth-notes').value = data.notes || '';
        const modalEl = document.getElementById('toothModal');
        const modal = new bootstrap.Modal(modalEl);
        modal.show();
    }

    window.toothChart = {
        init: init,
        saveTooth: function() {
            const data = {
                patient_id: patientId,
                tooth_number: parseInt(document.getElementById('tooth-number-input').value),
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
                    loadToothData(); // Reload to show changes
                } else {
                    alert('Error saving tooth');
                }
            })
            .catch(console.error);
        }
    };
})();