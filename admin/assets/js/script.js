// Sidebar Toggle
const sidebar = document.getElementById('sidebar');
const toggleBtn = document.getElementById('toggleBtn');

if (toggleBtn && sidebar) {
    toggleBtn.addEventListener('click', () => {
        sidebar.classList.toggle('collapsed');
    });
}

// Notification Dropdown
const notificationBtn = document.getElementById('notificationBtn');
const notificationDropdown = document.getElementById('notificationDropdown');

// Profile Dropdown
const profileBtn = document.getElementById('profileBtn');
const profileDropdown = document.getElementById('profileDropdown');

if (notificationBtn && notificationDropdown && profileDropdown) {
    notificationBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        notificationDropdown.classList.toggle('show');
        profileDropdown.classList.remove('show');
    });
}

if (profileBtn && profileDropdown && notificationDropdown) {
    profileBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        profileDropdown.classList.toggle('show');
        notificationDropdown.classList.remove('show');
    });
}

// Close dropdowns when clicking outside
if (notificationBtn && notificationDropdown && profileBtn && profileDropdown) {
    document.addEventListener('click', (e) => {
        if (!notificationDropdown.contains(e.target) && !notificationBtn.contains(e.target)) {
            notificationDropdown.classList.remove('show');
        }
        if (!profileDropdown.contains(e.target) && !profileBtn.contains(e.target)) {
            profileDropdown.classList.remove('show');
        }
    });
}

// Mark all notifications as read
const markReadBtn = document.querySelector('.mark-read');
const notificationItems = document.querySelectorAll('.notification-item');

if (markReadBtn && notificationItems.length) {
    markReadBtn.addEventListener('click', () => {
        notificationItems.forEach(item => {
            item.classList.remove('unread');
        });

        const notificationCount = document.querySelector('.notification-count');
        if (notificationCount) {
            notificationCount.textContent = '0';
            notificationCount.style.display = 'none';
        }
    });
}

// Individual notification click
if (notificationItems.length) {
    notificationItems.forEach(item => {
        item.addEventListener('click', () => {
            item.classList.remove('unread');
            updateNotificationCount();
        });
    });
}

function updateNotificationCount() {
    const unreadCount = document.querySelectorAll('.notification-item.unread').length;
    const notificationCount = document.querySelector('.notification-count');

    if (!notificationCount) {
        return;
    }

    if (unreadCount > 0) {
        notificationCount.textContent = unreadCount;
        notificationCount.style.display = 'flex';
    } else {
        notificationCount.style.display = 'none';
    }
}

// Chart.js - Maintenance Overview
const maintenanceCtx = document.getElementById('maintenanceChart');
let maintenanceChart = null;

if (maintenanceCtx && window.Chart) {
    const maintenanceGradient = maintenanceCtx.getContext('2d').createLinearGradient(0, 0, 0, 300);
    maintenanceGradient.addColorStop(0, 'rgba(37, 99, 235, 0.2)');
    maintenanceGradient.addColorStop(1, 'rgba(37, 99, 235, 0.01)');

    const fallbackDataset = {
        labels: (typeof maintenanceDates !== 'undefined') ? maintenanceDates : ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
        completed: (typeof maintenanceCompleted !== 'undefined') ? maintenanceCompleted : [0, 0, 0, 0, 0, 0, 0],
        scheduled: (typeof maintenanceScheduled !== 'undefined') ? maintenanceScheduled : [0, 0, 0, 0, 0, 0, 0],
        overdue: (typeof maintenanceOverdue !== 'undefined') ? maintenanceOverdue : [0, 0, 0, 0, 0, 0, 0],
    };
    const allMaintenanceDatasets = (typeof maintenanceDatasets !== 'undefined' && maintenanceDatasets) ? maintenanceDatasets : {};
    const initialPeriod = (typeof maintenancePeriod !== 'undefined' && maintenancePeriod) ? maintenancePeriod : 'weekly';

    function getMaintenanceDataset(period) {
        const selected = allMaintenanceDatasets[period];
        if (!selected || !Array.isArray(selected.labels)) {
            return fallbackDataset;
        }
        return {
            labels: Array.isArray(selected.labels) ? selected.labels : fallbackDataset.labels,
            completed: Array.isArray(selected.completed) ? selected.completed : fallbackDataset.completed,
            scheduled: Array.isArray(selected.scheduled) ? selected.scheduled : fallbackDataset.scheduled,
            overdue: Array.isArray(selected.overdue) ? selected.overdue : fallbackDataset.overdue,
        };
    }

    const initialDataset = getMaintenanceDataset(initialPeriod);

    maintenanceChart = new Chart(maintenanceCtx, {
        type: 'line',
        data: {
            labels: initialDataset.labels,
            datasets: [
                {
                    label: 'Completed',
                    data: initialDataset.completed,
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    borderWidth: 3,
                    tension: 0.4,
                    fill: true,
                    pointRadius: 5,
                    pointBackgroundColor: '#10b981',
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2,
                    pointHoverRadius: 7,
                },
                {
                    label: 'Scheduled',
                    data: initialDataset.scheduled,
                    borderColor: '#f59e0b',
                    backgroundColor: 'rgba(245, 158, 11, 0.1)',
                    borderWidth: 3,
                    tension: 0.4,
                    fill: true,
                    pointRadius: 5,
                    pointBackgroundColor: '#f59e0b',
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2,
                    pointHoverRadius: 7,
                },
                {
                    label: 'Overdue',
                    data: initialDataset.overdue,
                    borderColor: '#ef4444',
                    backgroundColor: 'rgba(239, 68, 68, 0.1)',
                    borderWidth: 3,
                    tension: 0.4,
                    fill: true,
                    pointRadius: 5,
                    pointBackgroundColor: '#ef4444',
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2,
                    pointHoverRadius: 7,
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    display: true,
                    position: 'top',
                    align: 'end',
                    labels: {
                        usePointStyle: true,
                        padding: 15,
                        font: {
                            size: 12,
                            weight: 500
                        }
                    }
                },
                tooltip: {
                    backgroundColor: '#1f2937',
                    titleColor: '#ffffff',
                    bodyColor: '#ffffff',
                    padding: 12,
                    borderColor: '#374151',
                    borderWidth: 1,
                    displayColors: true,
                    callbacks: {
                        label: function(context) {
                            return context.dataset.label + ': ' + context.parsed.y + ' tasks';
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: '#f3f4f6',
                        drawBorder: false
                    },
                    ticks: {
                        color: '#6b7280',
                        font: {
                            size: 12
                        },
                        padding: 8
                    }
                },
                x: {
                    grid: {
                        display: false,
                        drawBorder: false
                    },
                    ticks: {
                        color: '#6b7280',
                        font: {
                            size: 12
                        },
                        padding: 8
                    }
                }
            },
            interaction: {
                intersect: false,
                mode: 'index'
            }
        }
    });

    const chartFilter = document.querySelector('.chart-filter');
    if (chartFilter) {
        chartFilter.value = initialPeriod;
        chartFilter.addEventListener('change', (e) => {
            const selectedPeriod = e.target.value;
            const nextDataset = getMaintenanceDataset(selectedPeriod);
            maintenanceChart.data.labels = nextDataset.labels;
            maintenanceChart.data.datasets[0].data = nextDataset.completed;
            maintenanceChart.data.datasets[1].data = nextDataset.scheduled;
            maintenanceChart.data.datasets[2].data = nextDataset.overdue;
            maintenanceChart.update();
        });
    }
}

// Equipment Status Doughnut Chart
const equipmentStatusCtx = document.getElementById('equipmentStatusChart');
let equipmentStatusChart = null;

if (equipmentStatusCtx && window.Chart) {
    // Use global variables if available
    const statusLabels = (typeof equipmentStatusLabels !== 'undefined') ? equipmentStatusLabels : ['Operational', 'Under Maintenance', 'Broken Down', 'Inactive'];
    const statusData = (typeof equipmentStatusData !== 'undefined') ? equipmentStatusData : [0, 0, 0, 0];

    equipmentStatusChart = new Chart(equipmentStatusCtx, {
        type: 'doughnut',
        data: {
            labels: statusLabels,
            datasets: [{
                data: statusData,
                backgroundColor: [
                    '#10b981',
                    '#f59e0b',
                    '#ef4444',
                    '#9ca3af'
                ],
                borderWidth: 0,
                hoverOffset: 8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            cutout: '70%',
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    backgroundColor: '#1f2937',
                    titleColor: '#ffffff',
                    bodyColor: '#ffffff',
                    padding: 12,
                    borderColor: '#374151',
                    borderWidth: 1,
                    callbacks: {
                        label: function(context) {
                            return context.label + ': ' + context.parsed + ' units';
                        }
                    }
                }
            }
        }
    });
}

// Add smooth scroll for navigation
document.querySelectorAll('.nav-link').forEach(link => {
    link.addEventListener('click', (e) => {
        // Remove active class from all items
        document.querySelectorAll('.nav-item').forEach(item => {
            item.classList.remove('active');
        });
        
        // Add active class to clicked item
        link.parentElement.classList.add('active');
    });
});

// Quick action buttons
const actionButtons = document.querySelectorAll('.action-btn');
actionButtons.forEach(btn => {
    btn.addEventListener('click', () => {
        const action = btn.querySelector('span').textContent;
        showNotification(`Opening ${action}...`);
    });
});

// Show notification toast
function showNotification(message) {
    // Create toast element
    const toast = document.createElement('div');
    toast.className = 'toast-notification';
    toast.innerHTML = `
        <i class="fas fa-check-circle"></i>
        <span>${message}</span>
    `;
    
    // Add styles
    toast.style.cssText = `
        position: fixed;
        bottom: 30px;
        right: 30px;
        background: linear-gradient(135deg, #2563eb 0%, #3b82f6 100%);
        color: white;
        padding: 16px 24px;
        border-radius: 12px;
        box-shadow: 0 10px 25px rgba(37, 99, 235, 0.3);
        display: flex;
        align-items: center;
        gap: 12px;
        font-size: 14px;
        font-weight: 500;
        z-index: 10000;
        animation: slideIn 0.3s ease;
    `;
    
    document.body.appendChild(toast);
    
    // Remove after 3 seconds
    setTimeout(() => {
        toast.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => {
            document.body.removeChild(toast);
        }, 300);
    }, 3000);
}

// Add animation keyframes
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from {
            transform: translateX(400px);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    @keyframes slideOut {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(400px);
            opacity: 0;
        }
    }
`;
document.head.appendChild(style);

// Refresh button for chart
const refreshBtn = document.querySelector('.card-actions .icon-btn');
if (refreshBtn) {
    refreshBtn.addEventListener('click', () => {
        refreshBtn.querySelector('i').style.animation = 'spin 1s ease';
        
        // Reload page to fetch fresh data
        setTimeout(() => {
            location.reload();
        }, 500);
    });
}

// Add spin animation
const spinStyle = document.createElement('style');
spinStyle.textContent = `
    @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }
`;
document.head.appendChild(spinStyle);

// Mobile menu toggle
if (window.innerWidth <= 768 && sidebar) {
    const mobileTrigger = document.createElement('button');
    mobileTrigger.className = 'mobile-trigger';
    mobileTrigger.innerHTML = '<i class="fas fa-bars"></i>';
    mobileTrigger.style.cssText = `
        position: fixed;
        bottom: 20px;
        right: 20px;
        width: 56px;
        height: 56px;
        border-radius: 50%;
        background: linear-gradient(135deg, #2563eb 0%, #3b82f6 100%);
        border: none;
        color: white;
        font-size: 20px;
        box-shadow: 0 8px 16px rgba(37, 99, 235, 0.4);
        cursor: pointer;
        z-index: 999;
        display: none;
    `;
    
    if (window.innerWidth <= 768) {
        mobileTrigger.style.display = 'flex';
        mobileTrigger.style.alignItems = 'center';
        mobileTrigger.style.justifyContent = 'center';
    }
    
    document.body.appendChild(mobileTrigger);
    
    mobileTrigger.addEventListener('click', () => {
        sidebar.classList.toggle('show');
    });
}

// Responsive handling
window.addEventListener('resize', () => {
    if (window.innerWidth > 768) {
        sidebar.classList.remove('show');
    }
});

// Add loading animation to stat cards
const statCards = document.querySelectorAll('.stat-card');
statCards.forEach((card, index) => {
    card.style.opacity = '0';
    card.style.transform = 'translateY(20px)';
    
    setTimeout(() => {
        card.style.transition = 'all 0.5s ease';
        card.style.opacity = '1';
        card.style.transform = 'translateY(0)';
    }, index * 100);
});

// Animate progress bar
const progressFill = document.querySelector('.progress-fill');
if (progressFill) {
    const targetWidth = progressFill.style.width;
    progressFill.style.width = '0%';
    
    setTimeout(() => {
        progressFill.style.width = targetWidth;
    }, 500);
}

// Add hover effect to activity items
const activityItems = document.querySelectorAll('.activity-item');
activityItems.forEach(item => {
    item.addEventListener('mouseenter', () => {
        item.style.transform = 'translateX(8px)';
        item.style.transition = 'transform 0.3s ease';
    });
    
    item.addEventListener('mouseleave', () => {
        item.style.transform = 'translateX(0)';
    });
});

// Add hover effect to alert items
const alertItems = document.querySelectorAll('.alert-item');
alertItems.forEach(item => {
    item.addEventListener('click', () => {
        const title = item.querySelector('h4').textContent;
        showNotification(`Viewing: ${title}`);
    });
});

// System status real-time updates simulation
function updateSystemStatus() {
    const statusBadges = document.querySelectorAll('.status-badge.online');
    
    // Simulate status check every 30 seconds
    setInterval(() => {
        // Random status simulation (for demo purposes)
        const isOnline = Math.random() > 0.1; // 90% uptime
        
        statusBadges.forEach(badge => {
            if (!isOnline) {
                badge.textContent = 'Checking...';
                badge.style.background = '#fef3c7';
                badge.style.color = '#f59e0b';
            } else {
                badge.textContent = 'Online';
                badge.style.background = '#d1fae5';
                badge.style.color = '#10b981';
            }
        });
    }, 30000);
}

updateSystemStatus();

// Simulate real-time data updates (you'll replace this with actual API calls)
function simulateDataUpdates() {
    // Disabled simulation to use real PHP data
    /*
    setInterval(() => {
        if (!equipmentStatusChart) {
            return;
        }

        // Update equipment status chart with random data (replace with real API data)
        const operational = Math.floor(Math.random() * 50) + 20;
        const maintenance = Math.floor(Math.random() * 15) + 5;
        const broken = Math.floor(Math.random() * 10);
        const inactive = Math.floor(Math.random() * 10);
        
        equipmentStatusChart.data.datasets[0].data = [operational, maintenance, broken, inactive];
        equipmentStatusChart.update();
        
        // Update legend values
        const legendValues = document.querySelectorAll('.legend-value');
        if (legendValues.length >= 4) {
            legendValues[0].textContent = operational;
            legendValues[1].textContent = maintenance;
            legendValues[2].textContent = broken;
            legendValues[3].textContent = inactive;
        }
    }, 5000); // Update every 5 seconds (adjust as needed)
    */
}

// Start simulation (comment this out when you have real API integration)
simulateDataUpdates();

// Add tooltip functionality
const tooltips = document.querySelectorAll('[data-tooltip]');
tooltips.forEach(element => {
    element.addEventListener('mouseenter', (e) => {
        const tooltip = document.createElement('div');
        tooltip.className = 'tooltip';
        tooltip.textContent = element.getAttribute('data-tooltip');
        tooltip.style.cssText = `
            position: absolute;
            background: #1f2937;
            color: white;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 12px;
            white-space: nowrap;
            z-index: 10000;
            pointer-events: none;
        `;
        
        document.body.appendChild(tooltip);
        
        const rect = element.getBoundingClientRect();
        tooltip.style.top = rect.top - tooltip.offsetHeight - 8 + 'px';
        tooltip.style.left = rect.left + (rect.width - tooltip.offsetWidth) / 2 + 'px';
        
        element.addEventListener('mouseleave', () => {
            document.body.removeChild(tooltip);
        }, { once: true });
    });
});
