<script>
$(document).ready(function() {
    // Define your pages with multiple keywords and descriptions
    const searchData = [
        {
            title: "Dashboard",
            keywords: ["dashboard", "stats", "overview", "main", "statistics", "trends", "metrics", "analytics", "gender", "graphs"],
            url: "Admin_Dashboard.php"
        },
        {
            title: "Staff Management",
            keywords: ["staff", "user management", "account", "admin", "employees", "register"],
            url: "Staff_Man.php",
            // Only show to superadmin
            show: <?= ($user['id'] == 29 && $user['is_superadmin'] == 1) ? 'true' : 'false' ?>
        },
        {
            title: "Manage Students",
            keywords: ["students", "records", "learners", "pupils", "barcode"],
            url: "Manage_Students.php"
        },
        {
            title: "Manage Chatbot",
            keywords: ["chatbot", "questions", "prompts", "faq", "questions"],
            url: "Manage_Chatbot.php"
        },
        {
            title: "Attendance Logs",
            keywords: ["attendance", "logs", "records", "export", "excel", "pdf", "AM status", "PM status", "login", "logout"],
            url: "Attendance_logs.php"
        },
        {
            title: "Attendance Calendar",
            keywords: ["calendar", "attendance", "school year", "holiday", "attendance system"],
            url: "attendance_admin.php"
        },
        {
            title: "Absent Reports",
            keywords: ["absent", "reports", "sms", "verify", "approved reports", "pending reports", "sent"],
            url: "Admin_AbsentReport.php"
        },
        {
            title: "Home Page",
            keywords: ["home", "message", "welcome", "landing", "head teacher", "principal", "greeting", "school message", "welcome note", "downloads", "upload", "calendar event", "event", "activities", "downloadables"],
            url: "message.php"
        },
        {
            title: "News & Events",
            keywords: ["news", "events", "announcements", "updates"],
            url: "News_Eve.php"
        },
        {
            title: "Faculty & Staff",
            keywords: ["faculty", "staff", "teachers", "employees"],
            url: "Edit_Staff.php"
        },
        {
            title: "Achievements",
            keywords: ["achievements", "awards", "recognition", "school reports", "accomplishments", "projects"],
            url: "Edit_Achieve.php"
        },
        {
            title: "Organizational Chart",
            keywords: ["org chart", "structure", "hierarchy"],
            url: "chart_edit.php"
        },
        {
            title: "Contact",
            keywords: ["contact", "information", "address", "phone", "fblink", "facebook"],
            url: "Edit_Contact.php"
        },
        {
            title: "Gallery",
            keywords: ["gallery", "photos", "images", "pictures", "videos"],
            url: "Edit_Gallery.php"
        },
        {
            title: "History",
            keywords: ["history", "background", "story", "timeline"],
            url: "Edit_History.php"
        }
    ].filter(item => item.show !== false); // Filter out items that shouldn't be shown
    
    // Debounce function to limit search frequency
    const debounce = (func, delay) => {
        let debounceTimer;
        return function() {
            const context = this;
            const args = arguments;
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => func.apply(context, args), delay);
        };
    };
    
    // Search function
    const performSearch = debounce(function(searchTerm) {
        searchTerm = searchTerm.toLowerCase().trim();
        
        if (!searchTerm) {
            $('#searchResultsDropdown').remove();
            return;
        }
        
        // Find matches
        const matches = searchData.filter(item => 
            item.title.toLowerCase().includes(searchTerm) || 
            item.keywords.some(keyword => keyword.includes(searchTerm))
        ).slice(0, 5);
        
        showSearchResults(matches, searchTerm);
    }, 300);
    
    // Desktop search input event
    $('.navbar-search:not(#searchDropdown .navbar-search) input').on('input', function() {
        const searchTerm = $(this).val();
        if (!searchTerm) {
            $('#searchResultsDropdown').remove();
        } else {
            performSearch(searchTerm);
        }
    });
    
    // Desktop search button click
    $('.navbar-search:not(#searchDropdown .navbar-search) .btn').on('click', function(e) {
        e.preventDefault();
        const searchTerm = $(this).closest('.navbar-search').find('input').val().toLowerCase().trim();
        if (!searchTerm) return;
        
        const matches = searchData.filter(item => 
            item.title.toLowerCase().includes(searchTerm) || 
            item.keywords.some(keyword => keyword.includes(searchTerm))
        );
        
        if (matches.length === 1) {
            window.location.href = matches[0].url;
        } else if (matches.length > 1) {
            showSearchResults(matches, searchTerm);
        } else {
            showNoResults(searchTerm);
        }
    });
    
    // Desktop Enter key press
    $('.navbar-search:not(#searchDropdown .navbar-search) input').on('keypress', function(e) {
        if (e.which === 13) { // Enter key
            e.preventDefault();
            const searchTerm = $(this).val().toLowerCase().trim();
            if (!searchTerm) return;
            
            const matches = searchData.filter(item => 
                item.title.toLowerCase().includes(searchTerm) || 
                item.keywords.some(keyword => keyword.includes(searchTerm))
            );
            
            if (matches.length === 1) {
                window.location.href = matches[0].url;
            } else if (matches.length > 1) {
                showSearchResults(matches, searchTerm);
            } else {
                showNoResults(searchTerm);
            }
        }
    });
    
    function showSearchResults(results, searchTerm) {
        // Remove existing dropdown
        $('#searchResultsDropdown').remove();
        
        // Create dropdown HTML
        let html = `<div class="dropdown-menu show w-100" id="searchResultsDropdown" style="max-height: 300px; overflow-y: auto;">`;
        
        if (results.length === 0) {
            html += `<div class="dropdown-item text-muted">No results for "${searchTerm}"</div>`;
        } else {
            results.forEach(result => {
                html += `
                    <a class="dropdown-item" href="${result.url}">
                        <div class="d-flex justify-content-between">
                            <strong>${result.title}</strong>
                           
                        </div>
                        <small class="text-muted d-block">${result.keywords.slice(0, 3).join(', ')}</small>
                    </a>
                `;
            });
        }
        
        html += `</div>`;
        
        // Position and show dropdown
        const $input = $('.navbar-search input:focus');
        $input.after(html);
        
        // Keyboard navigation
        $input.off('keydown.searchnav').on('keydown.searchnav', function(e) {
            const $items = $('#searchResultsDropdown .dropdown-item');
            if (!$items.length) return;
            
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                const $active = $items.filter('.active').removeClass('active');
                const $next = $active.length ? $active.next() : $items.first();
                $next.addClass('active').focus();
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                const $active = $items.filter('.active').removeClass('active');
                const $prev = $active.length ? $active.prev() : $items.last();
                $prev.addClass('active').focus();
            } else if (e.key === 'Enter' && $items.filter('.active').length) {
                e.preventDefault();
                window.location.href = $items.filter('.active').attr('href');
            }
        });
    }
    
    function showNoResults(searchTerm) {
        Swal.fire({
            icon: 'info',
            title: 'No exact matches',
            html: `No results found for "<b>${searchTerm}</b>".<br><br>
                   Try searching for: 
                   <ul class="text-left">
                     <li>Dashboard</li>
                     <li>Manage Students</li>
                     <li>Attendance Logs</li>
                   </ul>`,
            confirmButtonColor: '#3085d6',
        });
    }
    
    // Close dropdown when clicking elsewhere
    $(document).on('click', function(e) {
        if (!$(e.target).closest('.navbar-search').length) {
            $('#searchResultsDropdown').remove();
        }
    });
    
    // Mobile search handling
    $('#searchDropdown').on('shown.bs.dropdown', function() {
        const $mobileSearch = $('#searchDropdown .navbar-search');
        const $mobileInput = $mobileSearch.find('input');
        
        // Focus on the input when dropdown is shown
        $mobileInput.focus();
        
        // Clear any existing dropdown when mobile search is opened
        $('#searchResultsDropdown').remove();
        
        // Mobile search input event
        $mobileInput.off('input.mobile').on('input.mobile', function() {
            const searchTerm = $(this).val();
            if (!searchTerm) {
                $('#searchResultsDropdown').remove();
            } else {
                performSearch(searchTerm);
            }
        });
        
        // Mobile search button click
        $mobileSearch.find('.btn').off('click.mobile').on('click.mobile', function(e) {
            e.preventDefault();
            const searchTerm = $mobileInput.val().toLowerCase().trim();
            if (!searchTerm) return;
            
            const matches = searchData.filter(item => 
                item.title.toLowerCase().includes(searchTerm) || 
                item.keywords.some(keyword => keyword.includes(searchTerm))
            );
            
            if (matches.length === 1) {
                window.location.href = matches[0].url;
            } else if (matches.length > 1) {
                showSearchResults(matches, searchTerm);
            } else {
                showNoResults(searchTerm);
            }
        });
        
        // Mobile Enter key press
        $mobileInput.off('keypress.mobile').on('keypress.mobile', function(e) {
            if (e.which === 13) { // Enter key
                e.preventDefault();
                const searchTerm = $(this).val().toLowerCase().trim();
                if (!searchTerm) return;
                
                const matches = searchData.filter(item => 
                    item.title.toLowerCase().includes(searchTerm) || 
                    item.keywords.some(keyword => keyword.includes(searchTerm))
                );
                
                if (matches.length === 1) {
                    window.location.href = matches[0].url;
                } else if (matches.length > 1) {
                    showSearchResults(matches, searchTerm);
                } else {
                    showNoResults(searchTerm);
                }
            }
        });
    });
    
    // Clean up when mobile search is closed
    $('#searchDropdown').on('hidden.bs.dropdown', function() {
        $('#searchResultsDropdown').remove();
        const $mobileSearch = $('#searchDropdown .navbar-search');
        $mobileSearch.find('input').off('input.mobile').val('');
        $mobileSearch.find('.btn').off('click.mobile');
        $mobileSearch.find('input').off('keypress.mobile');
    });
});
</script>