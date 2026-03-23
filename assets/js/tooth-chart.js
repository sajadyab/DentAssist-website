// Tooth Chart Module with SVG
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

        // Load the SVG template
        fetch('../assets/images/tooth-chart.svg')
            .then(response => response.text())
            .then(svgText => {
                container.innerHTML = svgText;
                applyToothData();
                attachEventListeners();
                addLegend();
            });
    }

    function applyToothData() {
        for (let i = 1; i <= 32; i++) {
            const tooth = document.getElementById(`tooth-${i}`);
            if (!tooth) continue;

            const data = toothData[i] || {};
            const status = data.status || 'healthy';
            
            // Apply status class
            tooth.classList.add(status);
            
            // Add notes indicator
            if (data.notes) {
                tooth.classList.add('has-notes');
            }
        }
    }

    function attachEventListeners() {
        for (let i = 1; i <= 32; i++) {
            const tooth = document.getElementById(`tooth-${i}`);
            if (tooth) {
                tooth.addEventListener('click', (e) => {
                    e.preventDefault();
                    openToothModal(parseInt(e.currentTarget.dataset.tooth || i));
                });
            }
        }
    }

    function addLegend() {
        const container = document.getElementById('tooth-chart-container');
        const legend = document.createElement('div');
        legend.className = 'tooth-legend';
        
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
            item.innerHTML = `
                <div class="legend-color" style="background-color: ${s.color}"></div>
                <span>${s.label}</span>
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
        new bootstrap.Modal(document.getElementById('toothModal')).show();
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