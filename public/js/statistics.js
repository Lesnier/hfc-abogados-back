const ShowStatistics = () => {
    return {
        init: () => {
            console.log('ShowStatistics');
            statusEmployerPieChart();
            employersByCompanyBarChart();
            employeeOnboardingLineChart();
            conditionEmployerDonutChart();
        }
    }
}

// Register ChartDataLabels if it exists
if (typeof ChartDataLabels !== 'undefined') {
    Chart.register(ChartDataLabels);
}

const statusEmployerPieChart = () => {
    const canvas = document.getElementById('statusEmployerPieChart');
    const ctx = canvas.getContext('2d');
    const chartData = JSON.parse(canvas.dataset.chartConfig);

    const myPieChart = new Chart(ctx, {
        type: 'pie',
        data: chartData,


        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'top',
                },
                title: {
                    display: true,
                    text: 'Estados de Empleados'
                },
                datalabels: {
                    color: (context) => {
                        const dataIndex = context.dataIndex;
                        const index = context.dataset.backgroundColor[dataIndex];
                        switch (index) {
                            case '#e3c06d':
                                return '#000000'; // Black for Gold (0)
                            case '#a53d3d':
                                return '#ffffff'; // White for Red (1)
                            case '#2c784f':
                                return '#ffffff'; // White for Green (2)
                            case '#c9c7c2':
                                return '#000000'; // Black for Grey (3)
                            default:
                                return '#000000'; // Default to Black
                        }
                    },
                    font: {
                        weight: 'normal'
                    },
                    formatter: (value, ctx) => {
                        let sum = 0;
                        let dataArr = ctx.chart.data.datasets[0].data;
                        dataArr.forEach(data => {
                            sum += data;
                        });
                        const percentage = (value * 100 / sum).toFixed(1);
                        return percentage === "0.0" ? '' : percentage + "%";
                    },
                }
            }
        }
    });
}

const employersByCompanyBarChart = () => {
    const canvas = document.getElementById('employersByCompanyBarChart');
    const ctx = canvas.getContext('2d');
    const chartData = JSON.parse(canvas.dataset.chartConfig);

    const myBarChart = new Chart(ctx, {
        type: 'bar',
        data: chartData,
        options: {
            indexAxis: 'y', // Makes the bars horizontal           
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                },
                title: {
                    display: true,
                    text: 'Empleados por Empresa (Acumulados)'
                },
                datalabels: {
                    color: (context) => {
                        const datasetIndex = context.datasetIndex;
                        if (datasetIndex === 0 || datasetIndex === 3) {
                            return '#000000'; // Black for Gold and Grey
                        }
                        return '#ffffff'; // White for Red and Green
                    },
                    font: {
                        weight: 'normal'
                    },
                    formatter: (value, ctx) => {
                        return value === 0 ? '' : value;
                    }
                }
            },
            scales: {
                x: {
                    stacked: true,
                    title: {
                        display: true,
                        text: 'Number of Employees'
                    }
                },
                y: {
                    stacked: true,
                    // title: {
                    //     display: true,
                    //     text: 'Company'
                    // }
                }
            }
        }
    });
}

const employeeOnboardingLineChart = () => {
    const canvas = document.getElementById('employeeOnboardingLineChart');
    const ctx = canvas.getContext('2d');
    const chartData = JSON.parse(canvas.dataset.chartConfig);

    const myLineChart = new Chart(ctx, {
        type: 'line',
        data: chartData,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                },
                title: {
                    display: true,
                    text: 'Contratación y Separación'
                },
                datalabels: {
                    align: 'end',
                    anchor: 'end',
                    offset: 2,
                    color: '#333',
                    font: {
                        weight: 'bold'
                    }
                }
            }
        }
    });
}

const conditionEmployerDonutChart = () => {
    const canvas = document.getElementById('conditionEmployerDonutChart');
    const ctx = canvas.getContext('2d');
    const chartData = JSON.parse(canvas.dataset.chartConfig);

    const myDonutChart = new Chart(ctx, {
        type: 'doughnut',
        data: chartData,
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'top',
                },
                title: {
                    display: true,
                    text: 'Condition of Employers'
                },
                datalabels: {
                    color: (context) => {
                        const index = context.dataIndex;
                        return index === 1 ? '#000000' : '#ffffff'; // Black for Gold (1), White for Navy (0)
                    },
                    font: {
                        weight: 'normal'
                    },
                    formatter: (value, ctx) => {
                        let sum = 0;
                        let dataArr = ctx.chart.data.datasets[0].data;
                        dataArr.map(data => {
                            sum += data;
                        });
                        let percentage = (value * 100 / sum).toFixed(1) + "%";
                        return percentage;
                    },
                }
            }
        }
    });
}


ShowStatistics().init();