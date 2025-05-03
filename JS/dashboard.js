
        document.addEventListener('DOMContentLoaded', function() {
            // Explicitly hide notification elements on page load
            document.getElementById('notification-dropdown').style.display = 'none';
            document.getElementById('notification-bar').style.display = 'none';
            
            // Mark document as JS initialized (for CSS)
            document.documentElement.classList.add('js-initialized');
            
            // Initialize notification system after ensuring elements are hidden
            setTimeout(() => {
                if (typeof notificationSystem !== 'undefined') {
                    notificationSystem.init();
                }
            }, 100);
        });

        document.addEventListener('DOMContentLoaded', async () => {
            // Explicitly hide the notification dropdown on page load
            document.getElementById('notification-dropdown').style.display = 'none';
            
            const loadingScreen = document.getElementById('loading-screen');
            const loadingVideo = document.getElementById('loading-video');
            const errorAlert = document.getElementById('profile-incomplete-alert');

            // Force hide loading screen after 3 seconds (fallback)
            const timeoutPromise = new Promise(resolve => setTimeout(resolve, 3000));

            try {
                // Fetch user data
                const dataPromise = fetch('../process/get_user_data.php', {
                    credentials: 'same-origin'
                }).then(response => response.json());

                // Wait for either data or timeout
                const data = await Promise.race([dataPromise, timeoutPromise]);

                if (data && data.success) {
                    // Update dashboard content
                    document.getElementById('user-greeting').textContent =
                        `Welcome, ${data.userName || 'User'}!`;

                    if (data.profileIncomplete) {
                        errorAlert.style.display = 'flex';
                    }

                    // Update counters
                    document.getElementById('medication-count').textContent =
                        `${data.medicationCount || 0} Active`;
                    document.getElementById('appointment-count').textContent =
                        `${data.appointmentCount || 0} Upcoming`;
                    document.getElementById('prescription-count').textContent =
                        `${data.prescriptionCount || 0} Total`;
                } else {
                    window.location.href = 'login.html';
                }
            } catch (error) {
                console.error('Error:', error);
                errorAlert.innerHTML =
                    '<i class="fas fa-exclamation-triangle"></i> Unable to load dashboard data.';
                errorAlert.style.display = 'flex';
            } finally {
                // Hide loading screen
                loadingScreen.style.display = 'none';
            }

            // Update year
            document.getElementById('current-year').textContent =
                new Date().getFullYear();

            // Fetch and display today's schedule
            showTodaySchedule();

            // Fetch profile image from backend
            try {
                const res = await fetch('../process/complete_profile_process.php');
                const data = await res.json();
                if (data && data.success && data.profile_image) {
                    document.getElementById('profile-picture').src = data.profile_image;
                    document.getElementById('profile-picture').style.display = 'inline-block';
                } else {
                    document.getElementById('profile-picture').style.display = 'none';
                }
            } catch (e) {
                document.getElementById('profile-picture').style.display = 'none';
            }
        });

        // Fetch all appointments and show the closest one
        async function showClosestAppointment() {
            try {
                const response = await fetch('../process/appointment_process.php', { credentials: 'same-origin' });
                const data = await response.json();
                const container = document.getElementById('upcoming-appointments-preview');
                const noUpcoming = document.getElementById('no-upcoming-appointments');
                container.innerHTML = '';
                noUpcoming.style.display = 'none';

                if (data.success && data.appointments && data.appointments.length) {
                    // Find the closest future appointment
                    const now = new Date();
                    const futureAppointments = data.appointments
                        .map(appt => ({
                            ...appt,
                            dateObj: new Date(`${appt.appointment_date}T${appt.appointment_time}`)
                        }))
                        .filter(appt => appt.dateObj > now)
                        .sort((a, b) => a.dateObj - b.dateObj);

                    if (futureAppointments.length) {
                        const appt = futureAppointments[0];
                        container.innerHTML = `
                            <div class="upcoming-appt-card" style="border:2px solid #4caf50; border-radius:10px; padding:16px; background:#f6fff6; margin-bottom:10px;">
                                <h4 style="margin:0 0 8px 0; color:#388e3c;">Next Appointment</h4>
                                <div><strong>Type:</strong> ${appt.appointment_type}</div>
                                <div><strong>Doctor:</strong> ${appt.doctor_name}</div>
                                <div><strong>Date:</strong> ${appt.appointment_date}</div>
                                <div><strong>Time:</strong> ${appt.appointment_time}</div>
                                <div><strong>Location:</strong> ${appt.location || 'N/A'}</div>
                                <div><strong>Notes:</strong> ${appt.notes || 'None'}</div>
                            </div>
                        `;
                    } else {
                        noUpcoming.style.display = 'block';
                    }
                } else {
                    noUpcoming.style.display = 'block';
                }
            } catch (e) {
                const noUpcoming = document.getElementById('no-upcoming-appointments');
                noUpcoming.textContent = 'Error loading appointments.';
                noUpcoming.style.display = 'block';
            }
        }
        showClosestAppointment();

        // Fetch and display today's schedule
        async function showTodaySchedule() {
            const today = new Date();
            const yyyy = today.getFullYear();
            const mm = String(today.getMonth() + 1).padStart(2, '0');
            const dd = String(today.getDate()).padStart(2, '0');
            const todayStr = `${yyyy}-${mm}-${dd}`;

            const container = document.getElementById('today-schedule');
            const noToday = document.getElementById('no-today-schedule');
            container.innerHTML = '';
            noToday.style.display = 'none';

            try {
                const response = await fetch('../process/schedule_process.php', { credentials: 'same-origin' });
                const data = await response.json();

                if (data.success && Array.isArray(data.events)) {
                    // Filter events for today
                    const events = data.events.filter(ev => {
                        let date = '';
                        if (ev.start) {
                            date = ev.start.split('T')[0];
                        }
                        return date === todayStr;
                    });

                    if (events.length) {
                        events.forEach(ev => {
                            let icon = ev.className === 'event-appointment' ? '🩺' : '💊';
                            let time = ev.start && ev.start.includes('T') ? ev.start.split('T')[1] : '';
                            let details = '';
                            if (ev.className === 'event-appointment') {
                                details = `
                                    <div><strong>Doctor:</strong> ${ev.title}</div>
                                    <div><strong>Time:</strong> ${time}</div>
                                    <div><strong>Location:</strong> ${ev.extendedProps.location || 'N/A'}</div>
                                    <div><strong>Notes:</strong> ${ev.extendedProps.notes || 'None'}</div>
                                `;
                            } else if (ev.className === 'event-medication') {
                                details = `
                                    <div><strong>Medication:</strong> ${ev.title}</div>
                                    <div><strong>Time:</strong> ${time}</div>
                                    <div><strong>Dosage:</strong> ${ev.extendedProps.dosage || 'N/A'}</div>
                                    <div><strong>Frequency:</strong> ${ev.extendedProps.frequency || 'N/A'}</div>
                                    <div><strong>Notes:</strong> ${ev.extendedProps.notes || 'None'}</div>
                                `;
                            }
                            container.innerHTML += `
                                <div class="event-card" style="border:1px solid #ddd; border-radius:8px; padding:10px; margin-bottom:8px; background:#f9f9f9;">
                                    <span style="font-size:1.2em;">${icon}</span>
                                    ${details}
                                </div>
                            `;
                        });
                    } else {
                        noToday.style.display = 'block';
                    }
                } else {
                    noToday.style.display = 'block';
                }
            } catch (e) {
                noToday.textContent = 'Error loading today\'s schedule.';
                noToday.style.display = 'block';
            }
        }

        const notificationSystem = {
            shownReminders: new Set(),
            nextPollTimeout: null,
            notificationBar: null,
            notificationDot: null,
            notificationDropdown: null,
            notificationList: null,
            MINUTE: 60000,
            HOUR: 3600000,

            // Status constants matching PHP
            STATUS_PENDING: 0,
            STATUS_DELIVERED: 1,
            STATUS_READ: 2,
            STATUS_ACKNOWLEDGED: 3,


            init() {
                // Initialize DOM elements
                this.notificationBar = document.getElementById('notification-bar');
                this.notificationDot = document.getElementById('notifications-dot');
                this.notificationDropdown = document.getElementById('notification-dropdown');
                this.notificationList = document.getElementById('notification-list');
                
                // Force hide notification elements
                this.hideDropdown();
                this.notificationBar.style.display = 'none';

                // Set up notification bell click handler
                const bell = document.getElementById('notifications-icon');
                const closeBtn = document.getElementById('close-dropdown');

                bell.onclick = (e) => {
                    e.stopPropagation();
                    this.toggleDropdown();
                };

                closeBtn.onclick = () => {
                    this.hideDropdown();
                };

                document.body.addEventListener('click', (e) => {
                    if (!this.notificationDropdown.contains(e.target) &&
                        !document.getElementById('notifications-icon').contains(e.target)) {
                        this.hideDropdown();
                    }
                });

                // Request notification permission
                if (window.Notification && Notification.permission !== "granted") {
                    Notification.requestPermission();
                }

                // Start polling for notifications
                this.pollForNotifications();

                // Set up polling interval (every minute)
                setInterval(() => this.pollForNotifications(), this.MINUTE);
            },

            toggleDropdown() {
                // Get the current computed style instead of checking inline style
                const isVisible = window.getComputedStyle(this.notificationDropdown).display !== 'none';

                if (isVisible) {
                    this.notificationDropdown.style.display = 'none';
                } else {
                    // Position the dropdown
                    const bell = document.getElementById('notifications-icon');
                    const bellRect = bell.getBoundingClientRect();
        
                    this.notificationDropdown.style.position = 'fixed';
                    this.notificationDropdown.style.top = (bellRect.bottom + window.scrollY) + 'px';
                    this.notificationDropdown.style.right = (window.innerWidth - bellRect.right) + 'px';
        
                    this.notificationDropdown.style.display = 'block';
                    this.loadNotifications();
                }
            },

            hideDropdown() {
                if (this.notificationDropdown) {
                    this.notificationDropdown.style.display = 'none';
                    // Remove any position-related inline styles
                    this.notificationDropdown.style.removeProperty('top');
                    this.notificationDropdown.style.removeProperty('right');
                }
            },

            async loadNotifications() {
                this.notificationList.innerHTML = '<div style="padding:20px;text-align:center;">Loading...</div>';

                try {
                    // Simple URL without pagination parameters
                    const url = '../process/notifications_process.php?action=get_notifications';

                    const res = await fetch(url, {
                        credentials: 'same-origin',
                        cache: 'no-cache'
                    });

                    if (!res.ok) {
                        throw new Error(`Server responded with status: ${res.status}`);
                    }

                    const data = await res.json();

                    if (data.success && data.notifications) {
                        this.notificationList.innerHTML = '';

                        if (data.notifications.length === 0) {
                            this.notificationList.innerHTML = '<div style="padding:20px;text-align:center;color:#888;">No notifications available.</div>';
                            this.updateNotificationDot(false);
                            return;
                        }

                        // Process and display notifications
                        data.notifications.forEach(notification => {
                            const item = document.createElement('div');
                            item.className = 'notification-item' + (notification.is_read ? '' : ' unread');
                            item.dataset.notificationId = notification.id;
                            
                            item.innerHTML = `
                    <div class="notification-content">
                        <strong>${this.escapeHtml(notification.title)}</strong><br>
                        <span style="font-size:0.95em;color:#666;">${this.escapeHtml(notification.message)}</span>
                    </div>
                    <div class="notification-time">
                        ${this.formatNotificationTime(notification.created_at)}
                    </div>
                `;

                            // Mark as read when clicked
                            item.addEventListener('click', () => {
                                this.markAsRead(notification.id);
                                item.classList.remove('unread');
                            });

                            this.notificationList.appendChild(item);
                        });

                        // Update notification dot based on unread count
                        const hasUnread = data.notifications.some(n => !n.is_read);
                        this.updateNotificationDot(hasUnread);
                    } else {
                        throw new Error(data.message || 'Failed to load notifications');
                    }
                } catch (e) {
                    console.error('Error loading notifications:', e);
                    this.notificationList.innerHTML = '<div style="padding:20px;text-align:center;color:#e53935;">Unable to load notifications. Please try again.</div>';
                }
            },

            formatNotificationTime(timestamp) {
                if (!timestamp) return '';

                const date = new Date(timestamp);
                const now = new Date();
                const diffMs = now - date;
                const diffMins = Math.floor(diffMs / 60000);
                const diffHours = Math.floor(diffMins / 60);
                const diffDays = Math.floor(diffHours / 24);

                if (diffMins < 1) return 'Just now';
                if (diffMins < 60) return `${diffMins}m ago`;
                if (diffHours < 24) return `${diffHours}h ago`;
                if (diffDays < 7) return `${diffDays}d ago`;

                return date.toLocaleDateString();
            },

            async markAsRead(notificationId) {
                try {
                    await fetch('../process/notifications_process.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `action=mark_read&notification_id=${notificationId}`
                    });

                    // Check if all notifications are now read
                    const unreadItems = this.notificationList.querySelectorAll('.notification-item.unread');
                    if (unreadItems.length === 1) { // If this was the last unread notification
                        this.updateNotificationDot(false);
                    }
                } catch (e) {
                    console.error('Error marking notification as read:', e);
                }
            },

            markAllAsRead() {
                const unreadItems = this.notificationList.querySelectorAll('.notification-item.unread');
                unreadItems.forEach(item => {
                    // Get the notification ID from a data attribute
                    const notificationId = item.dataset.notificationId;
                    if (notificationId) {
                        this.markAsRead(notificationId);
                        item.classList.remove('unread');
                    }
                });

                // Update the notification dot
                this.updateNotificationDot(false);
            },

            updateNotificationDot(show) {
                this.notificationDot.style.display = show ? 'block' : 'none';
            },

            async pollForNotifications() {
                try {
                    // Get both notifications and reminders
                    const res = await fetch('../process/notifications_process.php?action=check_notifications');
                    const data = await res.json();

                    if (!data.success) return;

                    // Update notification dot for static notifications
                    if (data.hasUnread) {
                        this.updateNotificationDot(true);
                    }

                    // Handle active reminders
                    if (data.activeReminders && Array.isArray(data.activeReminders)) {
                        data.activeReminders.forEach(reminder => {
                            const uniqueKey = `${reminder.type}-${reminder.reference_id}-${reminder.due_time}`;

                            if (!this.shownReminders.has(uniqueKey)) {
                                this.shownReminders.add(uniqueKey);
                                this.showReminder(reminder);

                                // Mark as delivered in backend
                                this.markReminderAsDelivered(reminder.id);
                            }
                        });
                    }

                    // Schedule next check based on upcoming reminders
                    if (data.nextReminderTime) {
                        const nextTime = new Date(data.nextReminderTime).getTime();
                        const now = Date.now();
                        const timeUntilNext = Math.max(nextTime - now, this.MINUTE);

                        // Clear existing timeout and set new one
                        if (this.nextPollTimeout) {
                            clearTimeout(this.nextPollTimeout);
                        }

                        this.nextPollTimeout = setTimeout(() => {
                            this.pollForNotifications();
                        }, Math.min(timeUntilNext, this.HOUR)); // Cap at 1 hour max
                    }
                } catch (e) {
                    console.error('Error polling for notifications:', e);
                }
            },

            showReminder(reminder) {
                // Show browser notification if permitted
                if (window.Notification && Notification.permission === "granted") {
                    new Notification(reminder.title, {
                        body: reminder.message,
                        icon: "../assets/favicon.png" // Use favicon as fallback
                    });
                }

                // Show in notification bar
                this.notificationBar.innerHTML = `
        <div>
            <strong>${reminder.title}</strong><br>
            <span>${reminder.message}</span>
        </div>
        <button onclick="notificationSystem.dismissNotificationBar(${reminder.id})" 
                style="margin-top:10px;padding:5px 10px;border:none;background:#ff9800;color:white;border-radius:4px;cursor:pointer;">
            Dismiss
        </button>
    `;
                this.notificationBar.style.display = 'block';

                // Auto-hide after 10 seconds
                setTimeout(() => {
                    if (this.notificationBar.style.display === 'block') {
                        this.dismissNotificationBar(reminder.id);
                    }
                }, 10000);
            },

            async markReminderAsDelivered(reminderId) {
                try {
                    await fetch('../process/notifications_process.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `action=mark_delivered&reminder_id=${reminderId}`
                    });
                } catch (e) {
                    console.error('Error marking reminder as delivered:', e);
                }
            },

            dismissNotificationBar(reminderId) {
                this.notificationBar.style.display = 'none';

                // Mark as acknowledged in backend
                if (reminderId) {
                    this.markReminderAsAcknowledged(reminderId);
                }
            },

            async markReminderAsAcknowledged(reminderId) {
                try {
                    await fetch('../process/notifications_process.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `action=mark_acknowledged&reminder_id=${reminderId}`
                    });
                } catch (e) {
                    console.error('Error marking reminder as acknowledged:', e);
                }
            },

            escapeHtml(text) {
                if (!text) return '';
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }
        };

        // Initialize notification system when DOM is loaded
        document.addEventListener('DOMContentLoaded', () => {
            notificationSystem.init();
            
            // Set up mark all read button
            document.getElementById('mark-all-read').addEventListener('click', () => {
                notificationSystem.markAllAsRead();
            });
            
            // Set up close button
            document.getElementById('close-dropdown').addEventListener('click', () => {
                notificationSystem.hideDropdown();
            });
        });

        // Cleanup on page unload
        window.addEventListener('beforeunload', function() {
            // Hide dropdowns before leaving the page
            document.getElementById('notification-dropdown').style.display = 'none';
            document.getElementById('notification-bar').style.display = 'none';
            
            // Clear any stored state that might persist
            sessionStorage.removeItem('notificationDropdownOpen');
        });

        // Dark Mode Implementation
        const darkModeToggle = document.getElementById('dark-mode-toggle');
        const body = document.body;

        // Function to enable dark mode
        function enableDarkMode() {
            body.classList.add('dark-mode');
            localStorage.setItem('darkMode', 'enabled');
            updateDarkModeIcon(true);
        }

        // Function to disable dark mode
        function disableDarkMode() {
            body.classList.remove('dark-mode');
            localStorage.setItem('darkMode', 'disabled');
            updateDarkModeIcon(false);
        }

        // Function to update the dark mode icon
        function updateDarkModeIcon(isDarkMode) {
            const icon = darkModeToggle.querySelector('i');
            if (isDarkMode) {
                icon.classList.remove('fa-moon');
                icon.classList.add('fa-sun');
            } else {
                icon.classList.remove('fa-sun');
                icon.classList.add('fa-moon');
            }
        }

        // Check if user previously enabled dark mode
        if (localStorage.getItem('darkMode') === 'enabled') {
            enableDarkMode();
        }

        // Toggle dark mode when the button is clicked
        darkModeToggle.addEventListener('click', () => {
            // Check if dark mode is currently enabled
            if (body.classList.contains('dark-mode')) {
                disableDarkMode();
            } else {
                enableDarkMode();
            }
        });

        // Check user's system preference for dark mode
        const prefersDarkScheme = window.matchMedia('(prefers-color-scheme: dark)');

        // Initial check for system preference (only if no user preference is stored)
        if (!localStorage.getItem('darkMode')) {
            if (prefersDarkScheme.matches) {
                enableDarkMode();
            } else {
                disableDarkMode();
            }
        }

        // Listen for changes in system preference
        prefersDarkScheme.addEventListener('change', (e) => {
            // Only apply if user hasn't set a preference
            if (!localStorage.getItem('darkMode')) {
                if (e.matches) {
                    enableDarkMode();
                } else {
                    disableDarkMode();
                }
            }
        });