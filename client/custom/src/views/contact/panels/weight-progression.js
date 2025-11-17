define([
    'views/record/panels/bottom',
    'lib!chart'
], (BottomPanelView, Chart) => {

    return class extends BottomPanelView {
        
        templateContent = `
            <div class="weight-evolution-panel">
                <div class="panel-header" style="margin-bottom: 15px;">
                    <h4>Gewicht evolutie</h4>
                </div>
                
                <!-- Filter Form -->
                <div class="filter-form" style="margin-bottom: 20px; padding: 15px; background: #f8f9fa; border-radius: 5px;">
                    <div class="row">
                        <div class="col-sm-3">
                            <label class="control-label">Aantal metingen</label>
                            <input type="number" class="form-control" name="limit" value="{{limit}}" min="1" max="200">
                        </div>
                        <div class="col-sm-3">
                            <label class="control-label">Grafiek start</label>
                            <input type="date" class="form-control" name="startDate" value="{{startDate}}">
                        </div>
                        <div class="col-sm-3">
                            <label class="control-label">Grafiek stop</label>
                            <input type="date" class="form-control" name="endDate" value="{{endDate}}">
                        </div>
                        <div class="col-sm-3" style="display: flex; align-items: end;">
                            <button type="button" class="btn btn-primary" data-action="applyFilters">
                                <span class="fas fa-filter"></span> Filter toepassen
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="weight-data-container">
                    {{#if hasData}}
                        <div class="overview-table" style="margin-bottom: 20px;">
                            <table class="table">
                                <thead>
                                    <tr style="background: #f8f9fa;">
                                        <th colspan="2">Eerste weging</th>
                                        <th colspan="2">Laatste weging</th>
                                        <th colspan="2">Resultaat</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>Datum:</td>
                                        <td>{{firstDate}}</td>
                                        <td>Datum:</td>
                                        <td>{{lastDate}}</td>
                                        <td>Dagen:</td>
                                        <td>{{daysDiff}}</td>
                                    </tr>
                                    <tr>
                                        <td>Gewicht:</td>
                                        <td>{{firstWeight}} kg</td>
                                        <td>Gewicht:</td>
                                        <td>{{lastWeight}} kg</td>
                                        <td>Evolutie:</td>
                                        <td style="color: {{evolutionColor}};">{{weightEvolution}} kg</td>
                                    </tr>
                                    <tr>
                                        <td>Spieren:</td>
                                        <td>{{firstMuscle}} kg</td>
                                        <td>Spieren:</td>
                                        <td>{{lastMuscle}} kg</td>
                                        <td>Evolutie:</td>
                                        <td style="color: {{muscleEvolutionColor}};">{{muscleEvolution}} kg</td>
                                    </tr>
                                    <tr>
                                        <td>Vet (%):</td>
                                        <td>{{firstFatPercentage}}%</td>
                                        <td>Vet (%):</td>
                                        <td>{{lastFatPercentage}}%</td>
                                        <td>Evolutie:</td>
                                        <td style="color: {{fatPercentageEvolutionColor}};">{{fatPercentageEvolution}}%</td>
                                    </tr>
                                    <tr>
                                        <td>Visceraal vet:</td>
                                        <td>{{firstVisceralFat}}</td>
                                        <td>Visceraal vet:</td>
                                        <td>{{lastVisceralFat}}</td>
                                        <td>Evolutie:</td>
                                        <td style="color: {{visceralFatEvolutionColor}};">{{visceralFatEvolution}}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="chart-container" style="position: relative; height: 300px; margin-bottom: 20px;">
                            <canvas id="{{panelId}}"></canvas>
                        </div>
                        <div class="chart-container" style="position: relative; height: 300px; margin-bottom: 20px;">
                            <canvas id="{{panelId}}_percentage"></canvas>
                        </div>
                        <div class="chart-container" style="position: relative; height: 300px; margin-bottom: 20px;">
                            <canvas id="{{panelId}}_kilogram"></canvas>
                        </div>
                        <div class="chart-container" style="position: relative; height: 300px; margin-bottom: 20px;">
                            <canvas id="{{panelId}}_metabool"></canvas>
                        </div>
                    {{else}}
                        <div class="alert alert-info">
                            <span class="fas fa-info-circle"></span>
                            No weight data available for this record
                        </div>
                    {{/if}}
                </div>
                {{#if loading}}
                    <div class="loading-indicator">
                        <span class="fas fa-spinner fa-spin"></span> Loading weight data...
                    </div>
                {{/if}}
            </div>
        `
        
        setup() {
            super.setup();
            this.panelId = 'weightEvolutionChart' + this.model.attributes.id;
            
            // Initialize data
            this.hasData = false;
            this.loading = false;
            this.weightData = [];
            this.chartInstance = null;
            
            // Initialize filter values
            this.limit = 60;
            this.startDate = '';
            this.endDate = '';
            
            // Load weight data
            this.loadWeightData();
        }
        
        loadWeightData() {
            this.loading = true;
            this.reRender();
            
            const recordId = this.model.id;
            
            const params = {
                where: [
                    {
                        type: 'equals',
                        attribute: 'contactId',
                        value: recordId
                    }
                ],
                orderBy: 'datum',
                order: 'asc',
                maxSize: this.limit
            };
            
            // Add date filters if provided
            if (this.startDate) {
                params.where.push({
                    type: 'greaterThanOrEquals',
                    attribute: 'datum',
                    value: this.startDate
                });
            }
            
            if (this.endDate) {
                params.where.push({
                    type: 'lessThanOrEquals',
                    attribute: 'datum',
                    value: this.endDate
                });
            }
            
            Espo.Ajax.getRequest('CBodyscan', params)
                .then(response => {
                    // Sort the data by date to ensure proper order
                    const sortedData = (response.list || []).sort((a, b) => {
                        return new Date(a.datum) - new Date(b.datum);
                    });
                    this.processWeightData(sortedData);
                })
                .catch(error => {
                    console.error('Error loading weight data:', error);
                })
                .finally(() => {
                    this.loading = false;
                    this.reRender();
                });
        }
        
        processWeightData(rawData) {
            if (!rawData || rawData.length === 0) {
                this.hasData = false;
                return;
            }
            
            this.weightData = rawData;
            this.hasData = true;
            
            // Calculate overview statistics
            const first = rawData[0];
            const last = rawData[rawData.length - 1];
            
            this.firstDate = this.formatDate(first.datum);
            this.lastDate = this.formatDate(last.datum);
            this.firstWeight = parseFloat(first.gewicht).toFixed(1);
            this.lastWeight = parseFloat(last.gewicht).toFixed(1);
            
            const weightChange = parseFloat(first.gewicht) - parseFloat(last.gewicht);
            this.weightEvolution = (weightChange >= 0 ? '-' : '+') + ' ' + Math.abs(weightChange).toFixed(1);
            this.evolutionColor = weightChange >= 0 ? 'green' : 'red';
            
            // Muscle mass evolution
            this.firstMuscle = parseFloat(first.spiermassa || 0).toFixed(1);
            this.lastMuscle = parseFloat(last.spiermassa || 0).toFixed(1);
            const muscleChange = parseFloat(first.spiermassa || 0) - parseFloat(last.spiermassa || 0);
            this.muscleEvolution = (muscleChange >= 0 ? '-' : '+') + ' ' + Math.abs(muscleChange).toFixed(1);
            this.muscleEvolutionColor = muscleChange >= 0 ? 'red' : 'green';
            
            // Body fat percentage evolution
            this.firstFatPercentage = parseFloat(first.vetPercentage || 0).toFixed(1);
            this.lastFatPercentage = parseFloat(last.vetPercentage || 0).toFixed(1);
            const fatPercentageChange = parseFloat(first.vetPercentage || 0) - parseFloat(last.vetPercentage || 0);
            this.fatPercentageEvolution = (fatPercentageChange >= 0 ? '-' : '+') + ' ' + Math.abs(fatPercentageChange).toFixed(1);
            this.fatPercentageEvolutionColor = fatPercentageChange >= 0 ? 'green' : 'red';
            
            // Visceral fat evolution
            this.firstVisceralFat = parseFloat(first.visceraalvet || 0).toFixed(1);
            this.lastVisceralFat = parseFloat(last.visceraalvet || 0).toFixed(1);
            const visceralFatChange = parseFloat(first.visceraalvet || 0) - parseFloat(last.visceraalvet || 0);
            this.visceralFatEvolution = (visceralFatChange >= 0 ? '-' : '+') + ' ' + Math.abs(visceralFatChange).toFixed(1);
            this.visceralFatEvolutionColor = visceralFatChange >= 0 ? 'green' : 'red';
            
            // Calculate days difference
            const firstDate = new Date(first.datum);
            const lastDate = new Date(last.datum);
            this.daysDiff = Math.abs(Math.ceil((lastDate - firstDate) / (1000 * 60 * 60 * 24)));
        }
        
        afterRender() {
            super.afterRender();
            
            if (this.hasData && !this.loading) {
                this.initializeChart();
            }
            
            // Bind filter events
            this.bindFilterEvents();
        }
        
        bindFilterEvents() {
            this.$el.find('[data-action="applyFilters"]').on('click', () => {
                this.applyFilters();
            });
        }
        
        applyFilters() {
            // Get filter values from form
            this.limit = parseInt(this.$el.find('input[name="limit"]').val()) || 60;
            this.startDate = this.$el.find('input[name="startDate"]').val() || '';
            this.endDate = this.$el.find('input[name="endDate"]').val() || '';
            
            // Reload data with new filters
            this.loadWeightData();
        }
        
        initializeChart() {
            if (this.gewichtChart) {
                this.gewichtChart.destroy();
            }
            if (this.percentageChart) {
                this.percentageChart.destroy();
            }
            if (this.kilogramChart) {
                this.kilogramChart.destroy();
            }
            if (this.metaboolChart) {
                this.metaboolChart.destroy();
            }
            
            const canvas = this.$el.find(`#${this.panelId}`)[0];
            const canvasPercentage = this.$el.find(`#${this.panelId}_percentage`)[0];
            const canvasKilogram = this.$el.find(`#${this.panelId}_kilogram`)[0];
            const canvasMetabool = this.$el.find(`#${this.panelId}_metabool`)[0];
            
            if (!canvas || !canvasPercentage || !canvasKilogram || !canvasMetabool) {
                console.error('Canvas elements not found');
                return;
            }

            require(['lib!client/custom/lib/chartjs-adapter-date-fns.js'], (dateFnsAdapter) => {
                // For time scale, we need to pass the actual date objects, not formatted strings
                const labels = this.weightData.map(item => new Date(item.datum));
                const weights = this.weightData.map(item => parseFloat(item.gewicht));
                const vetPercentage = this.weightData.map(item => parseFloat(item.vetPercentage || 0));
                const spierPercentage = this.weightData.map(item => parseFloat(item.spierpercentage || 0));
                const vetMassa = this.weightData.map(item => parseFloat(item.vetmassa || 0));
                const spierMassa = this.weightData.map(item => parseFloat(item.spiermassa || 0));
                const visceraalVet = this.weightData.map(item => parseFloat(item.visceraalvet || 0));
                const metabolischeLeeftijd = this.weightData.map(item => parseFloat(item.metabolischeleeftijd || 0));
                
                this.gewichtChart = new Chart(canvas.getContext('2d'), {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'Gewicht (kg)',
                            data: weights,
                            borderColor: 'rgb(75, 192, 192)',
                            backgroundColor: 'rgba(75, 192, 192, 0.2)',
                            tension: 0.1,
                            fill: true,
                            pointRadius: 5,
                            pointHoverRadius: 8
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        layout: {
                            padding: {
                                left: 30,
                                right: 30,
                                top: 0,
                                bottom: 0
                            }
                        },
                        scales: {
                            x: {
                                type: 'time',
                                time: {
                                    unit: 'month',
                                    stepSize: 2,
                                    displayFormats: {
                                        month: 'MMM yyyy',
                                        day: 'dd/MM/yyyy'
                                    }
                                },
                                ticks: {
                                    autoSkip: false
                                }
                            },
                            y: {
                                beginAtZero: false,
                                min: Math.floor(Math.min(...weights)) - 2,
                                max: Math.ceil(Math.max(...weights)) + 2
                            }
                        },
                        plugins: {
                            legend: {
                                display: true,
                                position: 'top'
                            },
                            tooltip: {
                                mode: 'index',
                                intersect: false,
                                callbacks: {
                                    label: function(context) {
                                        return `Gewicht: ${context.parsed.y.toFixed(1)} kg`;
                                    }
                                }
                            }
                        },
                        interaction: {
                            mode: 'nearest',
                            axis: 'x',
                            intersect: false
                        }
                    }
                });

                const minFatPercentageY = Math.floor(Math.min(...vetPercentage)) - 2;
                const maxFatPercentageY = Math.ceil(Math.max(...vetPercentage)) + 2;
                const minMusclePercentageY = Math.floor(Math.min(...spierPercentage)) - 2;
                const maxMusclePercentageY = Math.ceil(Math.max(...spierPercentage)) + 2;
                
                this.percentageChart = new Chart(canvasPercentage.getContext('2d'), {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [
                            {
                                label: 'Vet (%)',
                                data: vetPercentage,
                                borderColor: 'rgba(255, 205, 86, 1)',
                                backgroundColor: 'rgba(255, 205, 86, 0.2)',
                                yAxisID: 'y2',
                                tension: 0.1,
                                pointRadius: 5,
                                pointHoverRadius: 8
                            },
                            {
                                label: 'Spier (%)',
                                data: spierPercentage,
                                borderColor: 'rgba(255, 99, 132, 1)',
                                backgroundColor: 'rgba(255, 99, 132, 0.2)',
                                yAxisID: 'y',
                                tension: 0.1,
                                pointRadius: 5,
                                pointHoverRadius: 8
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        layout: {
                            padding: {
                                left: 30,
                                right: 30,
                                top: 0,
                                bottom: 0
                            }
                        },
                        scales: {
                            x: {
                                type: 'time',
                                time: {
                                    unit: 'month',
                                    stepSize: 2,
                                    displayFormats: {
                                        month: 'MMM yyyy',
                                        day: 'dd/MM/yyyy'
                                    }
                                },
                                ticks: {
                                    autoSkip: false
                                }
                            },
                            y: {
                                type: 'linear',
                                position: 'left',
                                title: { display: true, text: 'Spieren (%)' },
                                min: minMusclePercentageY,
                                max: maxMusclePercentageY
                            },
                            y2: {
                                type: 'linear',
                                position: 'right',
                                title: { display: true, text: 'Vet (%)' },
                                grid: { drawOnChartArea: false },
                                min: minFatPercentageY,
                                max: maxFatPercentageY
                            }
                        },
                        plugins: {
                            legend: {
                                display: true,
                                position: 'top'
                            },
                            tooltip: {
                                mode: 'index',
                                intersect: false
                            }
                        },
                        interaction: {
                            mode: 'nearest',
                            axis: 'x',
                            intersect: false
                        }
                    }
                });

                const minFatKgY = Math.floor(Math.min(...vetMassa)) - 2;
                const maxFatKgY = Math.ceil(Math.max(...vetMassa)) + 2;
                const minMuscleKgY = Math.floor(Math.min(...spierMassa)) - 2;
                const maxMuscleKgY = Math.ceil(Math.max(...spierMassa)) + 2;
                
                this.kilogramChart = new Chart(canvasKilogram.getContext('2d'), {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [
                            {
                                label: 'Vet (kg)',
                                data: vetMassa,
                                borderColor: 'rgba(255, 205, 86, 1)',
                                backgroundColor: 'rgba(255, 205, 86, 0.2)',
                                yAxisID: 'y2',
                                tension: 0.1,
                                pointRadius: 5,
                                pointHoverRadius: 8
                            },
                            {
                                label: 'Spier (kg)',
                                data: spierMassa,
                                borderColor: 'rgba(255, 99, 132, 1)',
                                backgroundColor: 'rgba(255, 99, 132, 0.2)',
                                yAxisID: 'y',
                                tension: 0.1,
                                pointRadius: 5,
                                pointHoverRadius: 8
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        layout: {
                            padding: {
                                left: 30,
                                right: 30,
                                top: 0,
                                bottom: 0
                            }
                        },
                        scales: {
                            x: {
                                type: 'time',
                                time: {
                                    unit: 'month',
                                    stepSize: 2,
                                    displayFormats: {
                                        month: 'MMM yyyy',
                                        day: 'dd/MM/yyyy'
                                    }
                                },
                                ticks: {
                                    autoSkip: false
                                }
                            },
                            y: {
                                type: 'linear',
                                position: 'left',
                                title: { display: true, text: 'Spieren (kg)' },
                                min: minMuscleKgY,
                                max: maxMuscleKgY
                            },
                            y2: {
                                type: 'linear',
                                position: 'right',
                                title: { display: true, text: 'Vet (kg)' },
                                grid: { drawOnChartArea: false },
                                min: minFatKgY,
                                max: maxFatKgY
                            }
                        },
                        plugins: {
                            legend: {
                                display: true,
                                position: 'top'
                            },
                            tooltip: {
                                mode: 'index',
                                intersect: false
                            }
                        },
                        interaction: {
                            mode: 'nearest',
                            axis: 'x',
                            intersect: false
                        }
                    }
                });

                const minMetabolischeLftY = Math.floor(Math.min(...metabolischeLeeftijd)) - 2;
                const maxMetabolischeLftY = Math.ceil(Math.max(...metabolischeLeeftijd)) + 2;

                const minVisceraalVetY = Math.max(Math.floor(Math.min(...visceraalVet)) - 2, 0);
                const maxVisceraalVetY = Math.ceil(Math.max(...visceraalVet)) + 2;
                
                this.metaboolChart = new Chart(canvasMetabool.getContext('2d'), {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [
                            {
                                label: 'Visceraal vet',
                                data: visceraalVet,
                                borderColor: 'rgba(255, 205, 86, 1)',
                                backgroundColor: 'rgba(255, 99, 132, 0.2)',
                                yAxisID: 'y2',
                                tension: 0.1,
                                pointRadius: 5,
                                pointHoverRadius: 8
                            },
                            {
                                label: 'Metabolische leeftijd',
                                data: metabolischeLeeftijd,
                                borderColor: 'rgb(75, 192, 192)',
                                backgroundColor: 'rgba(75, 192, 192, 0.2)',
                                yAxisID: 'y',
                                tension: 0.1,
                                pointRadius: 5,
                                pointHoverRadius: 8
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        layout: {
                            padding: {
                                left: 30,
                                right: 30,
                                top: 0,
                                bottom: 0
                            }
                        },
                        scales: {
                            x: {
                                type: 'time',
                                time: {
                                    unit: 'month',
                                    stepSize: 2,
                                    displayFormats: {
                                        month: 'MMM yyyy',
                                        day: 'dd/MM/yyyy'
                                    }
                                },
                                ticks: {
                                    autoSkip: false
                                }
                            },
                            y: {
                                type: 'linear',
                                position: 'left',
                                title: { display: true, text: 'Metabolische leeftijd' },
                                min: minMetabolischeLftY,
                                max: maxMetabolischeLftY
                            },
                            y2: {
                                type: 'linear',
                                position: 'right',
                                title: { display: true, text: 'Visceraal vet' },
                                grid: { drawOnChartArea: false },
                                min: minVisceraalVetY,
                                max: maxVisceraalVetY
                            }
                        },
                        plugins: {
                            legend: {
                                display: true,
                                position: 'top'
                            },
                            tooltip: {
                                mode: 'index',
                                intersect: false
                            }
                        },
                        interaction: {
                            mode: 'nearest',
                            axis: 'x',
                            intersect: false
                        }
                    }
                });
            });
        }

        
        
        formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('en-GB'); // DD/MM/YYYY format
        }
        
        // Action handlers
        actionRefreshWeightData() {
            this.loadWeightData();
        }
        
        data() {
            return {
                ...super.data(),
                hasData: this.hasData,
                loading: this.loading,
                panelId: this.panelId,
                limit: this.limit,
                startDate: this.startDate,
                endDate: this.endDate,
                firstDate: this.firstDate,
                lastDate: this.lastDate,
                firstWeight: this.firstWeight,
                lastWeight: this.lastWeight,
                weightEvolution: this.weightEvolution,
                evolutionColor: this.evolutionColor,
                daysDiff: this.daysDiff,
                firstMuscle: this.firstMuscle,
                lastMuscle: this.lastMuscle,
                muscleEvolution: this.muscleEvolution,
                muscleEvolutionColor: this.muscleEvolutionColor,
                firstFatPercentage: this.firstFatPercentage,
                lastFatPercentage: this.lastFatPercentage,
                fatPercentageEvolution: this.fatPercentageEvolution,
                fatPercentageEvolutionColor: this.fatPercentageEvolutionColor,
                firstVisceralFat: this.firstVisceralFat,
                lastVisceralFat: this.lastVisceralFat,
                visceralFatEvolution: this.visceralFatEvolution,
                visceralFatEvolutionColor: this.visceralFatEvolutionColor
            };
        }
        
        // Clean up chart when panel is removed
        remove() {
            // Unbind filter events
            this.$el.find('[data-action="applyFilters"]').off('click');
            
            if (this.gewichtChart) {
                this.gewichtChart.destroy();
            }
            if (this.percentageChart) {
                this.percentageChart.destroy();
            }
            if (this.kilogramChart) {
                this.kilogramChart.destroy();
            }
            if (this.metaboolChart) {
                this.metaboolChart.destroy();
            }
            super.remove();
        }
    }
});
