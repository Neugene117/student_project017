 <!-- Header -->
        <header class="header">
            <div class="header-left">
                <button class="toggle-btn" id="toggleBtn">
                    <i class="fas fa-bars"></i>
                </button>
                <h1 class="page-title">Dashboard Overview</h1>
            </div>
            
            <div class="header-right">
                <!-- Notifications -->
                <div class="notification-wrapper">
                    <button class="notification-btn" id="notificationBtn">
                        <i class="fas fa-bell"></i>
                        <span class="notification-count">5</span>
                    </button>
                    
                    <div class="notification-dropdown" id="notificationDropdown">
                        <div class="dropdown-header">
                            <h3>Notifications</h3>
                            <button class="mark-read">Mark all as read</button>
                        </div>
                        <div class="notification-list">
                            <div class="notification-item unread">
                                <div class="notification-icon breakdown">
                                    <i class="fas fa-exclamation-circle"></i>
                                </div>
                                <div class="notification-content">
                                    <p class="notification-text"><strong>High Priority:</strong> Equipment breakdown reported</p>
                                    <span class="notification-time">30 minutes ago</span>
                                </div>
                            </div>
                            <div class="notification-item unread">
                                <div class="notification-icon maintenance">
                                    <i class="fas fa-wrench"></i>
                                </div>
                                <div class="notification-content">
                                    <p class="notification-text">Scheduled maintenance due for <strong>3 equipment</strong></p>
                                    <span class="notification-time">2 hours ago</span>
                                </div>
                            </div>
                            <div class="notification-item unread">
                                <div class="notification-icon expired">
                                    <i class="fas fa-calendar-times"></i>
                                </div>
                                <div class="notification-content">
                                    <p class="notification-text">Equipment warranty expiring soon</p>
                                    <span class="notification-time">5 hours ago</span>
                                </div>
                            </div>
                            <div class="notification-item">
                                <div class="notification-icon user">
                                    <i class="fas fa-user-plus"></i>
                                </div>
                                <div class="notification-content">
                                    <p class="notification-text">New user registered: <strong>John Technician</strong></p>
                                    <span class="notification-time">1 day ago</span>
                                </div>
                            </div>
                            <div class="notification-item">
                                <div class="notification-icon success">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                                <div class="notification-content">
                                    <p class="notification-text">Maintenance completed successfully</p>
                                    <span class="notification-time">2 days ago</span>
                                </div>
                            </div>
                        </div>
                        <div class="dropdown-footer">
                            <a href="#">View all notifications</a>
                        </div>
                    </div>
                </div>

                <!-- Profile -->
                <div class="profile-wrapper">
                    <button class="profile-btn" id="profileBtn">
                        <img src="https://ui-avatars.com/api/?name=Admin+User&background=2563eb&color=fff" alt="Profile" class="profile-img">
                        <span class="profile-name">Admin User</span>
                        <i class="fas fa-chevron-down"></i>
                    </button>
                    
                    <div class="profile-dropdown" id="profileDropdown">
                        <div class="profile-header">
                            <img src="https://ui-avatars.com/api/?name=Admin+User&background=2563eb&color=fff" alt="Profile">
                            <div class="profile-info">
                                <h4>Admin User</h4>
                                <p>admin@equipment.com</p>
                            </div>
                        </div>
                        <ul class="profile-menu">
                            <li>
                                <a href="#">
                                    <i class="fas fa-user"></i>
                                    <span>My Profile</span>
                                </a>
                            </li>
                            <li>
                                <a href="#">
                                    <i class="fas fa-question-circle"></i>
                                    <span>Help & Support</span>
                                </a>
                            </li>
                            <li class="divider"></li>
                            <li>
                                <a href="#" class="logout">
                                    <i class="fas fa-sign-out-alt"></i>
                                    <span>Logout</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </header>