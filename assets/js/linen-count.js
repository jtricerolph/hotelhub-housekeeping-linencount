/**
 * Linen Count Module - Frontend JavaScript
 *
 * Handles linen count UI interactions, submissions, and real-time updates
 */

(function($) {
    'use strict';

    // State management
    let currentLinenState = {
        location_id: null,
        room_id: null,
        date: null,
        isLocked: false,
        originalCounts: {},
        currentCounts: {},
        autoSaveTimers: {}
    };

    // Debounce utility function
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    /**
     * Initialize linen count functionality
     */
    function initLinenCount() {
        // Initialize event handlers
        initLinenCountButtons();
        initLinenSubmitHandler();
        initLinenEditHandler();
        initLinenHeartbeat();
    }

    /**
     * Initialize count up/down buttons
     */
    function initLinenCountButtons() {
        console.log('HHLC: Initializing linen count buttons');

        // Count up button
        $(document).on('click', '.linen-count-up:not(:disabled)', function(e) {
            e.preventDefault();
            console.log('HHLC: Count up clicked');
            const $item = $(this).closest('.hhlc-linen-item');
            const $input = $item.find('.linen-count-value');
            const currentValue = parseInt($input.val()) || 0;
            const newValue = currentValue + 1;

            console.log('HHLC: Incrementing from', currentValue, 'to', newValue);
            $input.val(newValue);
            updateLinenCount($item.data('item-id'), newValue);
            highlightChangedItem($item, $input);
        });

        // Count down button
        $(document).on('click', '.linen-count-down:not(:disabled)', function(e) {
            e.preventDefault();
            console.log('HHLC: Count down clicked');
            const $item = $(this).closest('.hhlc-linen-item');
            const $input = $item.find('.linen-count-value');
            const currentValue = parseInt($input.val()) || 0;
            const newValue = Math.max(0, currentValue - 1);

            console.log('HHLC: Decrementing from', currentValue, 'to', newValue);
            $input.val(newValue);
            updateLinenCount($item.data('item-id'), newValue);
            highlightChangedItem($item, $input);
        });

        // Direct input change
        $(document).on('change', '.hhlc-linen-item .linen-count-value', function() {
            const $item = $(this).closest('.hhlc-linen-item');
            const value = Math.max(0, parseInt($(this).val()) || 0);
            $(this).val(value);
            updateLinenCount($item.data('item-id'), value);
            highlightChangedItem($item, $(this));
        });

        // Touch-friendly enhancements
        if ('ontouchstart' in window) {
            // Add touch feedback
            $(document).on('touchstart', '.linen-count-up, .linen-count-down', function() {
                $(this).addClass('touched');
            });

            $(document).on('touchend touchcancel', '.linen-count-up, .linen-count-down', function() {
                $(this).removeClass('touched');
            });

            // Prevent double-tap zoom on buttons
            $(document).on('touchend', '.linen-count-up, .linen-count-down', function(e) {
                e.preventDefault();
                $(this).click();
            });
        }
    }

    /**
     * Highlight items that have been changed from original value
     */
    function highlightChangedItem($item, $input) {
        const originalValue = parseInt($input.data('original')) || 0;
        const currentValue = parseInt($input.val()) || 0;

        if (currentValue !== originalValue) {
            $item.addClass('changed');
        } else {
            $item.removeClass('changed');
        }

        // Update the submit button state
        updateSubmitButtonState();
    }

    /**
     * Update the state of the submit button
     */
    function updateSubmitButtonState() {
        const $submitBtn = $('.hhlc-submit-linen-count');
        const hasChanges = $('.hhlc-linen-item.changed').length > 0;

        if (hasChanges) {
            $submitBtn.removeClass('button').addClass('button-primary');
        } else {
            $submitBtn.removeClass('button-primary').addClass('button');
        }
    }

    /**
     * Update linen count in state and trigger auto-save
     */
    function updateLinenCount(itemId, count) {
        if (!currentLinenState.currentCounts) {
            currentLinenState.currentCounts = {};
        }
        currentLinenState.currentCounts[itemId] = count;

        // Update the room list badge
        updateRoomListBadge();

        // Trigger immediate heartbeat check to fetch any pending updates before saving
        // This helps prevent conflicts when multiple users are editing simultaneously
        if (typeof wp !== 'undefined' && wp.heartbeat) {
            console.log('HHLC: Triggering immediate heartbeat check before save');
            wp.heartbeat.connectNow();
        }

        // Trigger auto-save if we have location data
        if (currentLinenState.location_id && currentLinenState.room_id && currentLinenState.date) {
            console.log('HHLC: Triggering auto-save for item', itemId, 'with count', count);
            debouncedAutoSave(itemId, count);
        } else {
            console.warn('HHLC: Cannot auto-save - state not initialized', {
                has_location: !!currentLinenState.location_id,
                has_room: !!currentLinenState.room_id,
                has_date: !!currentLinenState.date
            });
        }
    }

    /**
     * Update the linen count badge in the room list
     */
    function updateRoomListBadge() {
        if (!currentLinenState.room_id) {
            return;
        }

        // Find the room row in the list
        const $roomRow = $('.hhdl-room-row[data-room-id="' + currentLinenState.room_id + '"]');
        if (!$roomRow.length) {
            return;
        }

        // Calculate total count from all items
        let totalCount = 0;
        Object.values(currentLinenState.currentCounts).forEach(function(count) {
            totalCount += parseInt(count) || 0;
        });

        // Find the linen badge
        const $linenBadge = $roomRow.find('.hhdl-linen-count-badge');
        const $linenStatus = $roomRow.find('.hhdl-linen-status');

        if ($linenBadge.length) {
            // Update badge number
            $linenBadge.text(totalCount);

            // Update status classes based on lock state
            if (currentLinenState.isLocked) {
                $linenStatus.removeClass('hhdl-linen-unsaved').addClass('hhdl-linen-saved');
                $linenStatus.attr('title', totalCount + ' linen items (saved)');
            } else {
                $linenStatus.removeClass('hhdl-linen-saved').addClass('hhdl-linen-unsaved');
                $linenStatus.attr('title', totalCount + ' linen items (unsaved)');
            }

            // Hide badge if count is 0
            if (totalCount === 0) {
                $linenBadge.hide();
            } else {
                $linenBadge.show();
            }
        }
    }

    /**
     * Auto-save linen count to database
     */
    function autoSaveLinenCount(itemId, count) {
        console.log('HHLC: autoSaveLinenCount called', {itemId, count});

        const $item = $('.hhlc-linen-item[data-item-id="' + itemId + '"]');
        const $saveStatus = $item.find('.linen-save-status');

        console.log('HHLC: Found item element:', $item.length > 0);

        // Show saving indicator
        if ($saveStatus.length === 0) {
            $item.find('.linen-count-controls').append('<span class="linen-save-status">Saving...</span>');
        } else {
            $saveStatus.text('Saving...').removeClass('saved').addClass('saving');
        }

        const bookingRef = $('#hhdl-modal').data('booking-ref') || '';

        console.log('HHLC: Sending auto-save AJAX request', {
            location_id: currentLinenState.location_id,
            room_id: currentLinenState.room_id,
            date: currentLinenState.date,
            item_id: itemId,
            count: count
        });

        $.ajax({
            url: hhlcAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'hhlc_autosave_linen_count',
                nonce: hhlcAjax.nonce,
                location_id: currentLinenState.location_id,
                room_id: currentLinenState.room_id,
                date: currentLinenState.date,
                item_id: itemId,
                count: count,
                booking_ref: bookingRef
            },
            success: function(response) {
                console.log('HHLC: Auto-save AJAX success', response);
                const $status = $('.hhlc-linen-item[data-item-id="' + itemId + '"]').find('.linen-save-status');
                if (response.success) {
                    console.log('HHLC: Auto-save successful');
                    $status.text('Saved').removeClass('saving').addClass('saved');
                    setTimeout(function() {
                        $status.fadeOut(300, function() {
                            $(this).remove();
                        });
                    }, 2000);
                } else {
                    console.error('HHLC: Auto-save failed', response.data);
                    $status.text('Save failed').removeClass('saving').addClass('error');
                }
            },
            error: function(xhr, status, error) {
                console.error('HHLC: Auto-save AJAX error', {xhr, status, error});
                const $status = $('.hhlc-linen-item[data-item-id="' + itemId + '"]').find('.linen-save-status');
                $status.text('Save failed').removeClass('saving').addClass('error');
            }
        });
    }

    // Per-item debounced auto-save
    // This ensures each item has its own debounce timer, so updating multiple items
    // quickly doesn't cancel previous items' saves
    const autoSaveTimers = {};
    function debouncedAutoSave(itemId, count) {
        // Clear existing timer for this item
        if (autoSaveTimers[itemId]) {
            clearTimeout(autoSaveTimers[itemId]);
        }

        // Set new timer for this specific item
        autoSaveTimers[itemId] = setTimeout(function() {
            autoSaveLinenCount(itemId, count);
            delete autoSaveTimers[itemId];
        }, 800);
    }

    /**
     * Initialize submit handler
     */
    function initLinenSubmitHandler() {
        $(document).on('click', '.hhlc-submit-linen-count', function(e) {
            e.preventDefault();

            const $button = $(this);
            const $section = $button.closest('.hhlc-linen-controls');
            const $status = $section.find('.hhlc-linen-status');

            // Collect current counts
            const counts = {};
            $section.find('.hhlc-linen-item').each(function() {
                const itemId = $(this).data('item-id');
                const count = parseInt($(this).find('.linen-count-value').val()) || 0;
                counts[itemId] = count;
            });

            // Get room info from modal or section
            const locationId = $section.data('location');
            const roomId = $section.data('room');
            const date = $section.data('date');
            const bookingRef = $('#hhdl-modal').data('booking-ref') || '';

            // Show loading state
            $button.prop('disabled', true).text('Submitting...');
            $status.html('<span class="spinner is-active"></span> Submitting counts...');

            // Submit via AJAX
            $.ajax({
                url: hhlcAjax.ajax_url,
                type: 'POST',
                data: {
                    action: 'hhlc_submit_linen_count',
                    nonce: hhlcAjax.nonce,
                    location_id: locationId,
                    room_id: roomId,
                    date: date,
                    counts: JSON.stringify(counts),
                    booking_ref: bookingRef
                },
                success: function(response) {
                    if (response.success) {
                        // Update UI to locked state
                        $section.addClass('locked');
                        $section.find('button.linen-count-up, button.linen-count-down').prop('disabled', true);
                        $section.find('.linen-count-value').prop('readonly', true);

                        // Update state to locked
                        currentLinenState.isLocked = true;

                        // Change button to edit mode
                        $button.removeClass('button-primary hhlc-submit-linen-count')
                               .addClass('hhlc-edit-linen-count')
                               .text('Edit');

                        // Update original values
                        $section.find('.linen-count-value').each(function() {
                            $(this).data('original', $(this).val());
                        });

                        // Remove changed highlights
                        $section.find('.hhlc-linen-item').removeClass('changed');

                        // Update metadata
                        let metadataHtml = '<small>Submitted by ' + response.data.submitted_by +
                                         ' at ' + response.data.submitted_at + '</small>';

                        if ($section.find('.hhlc-linen-metadata').length) {
                            $section.find('.hhlc-linen-metadata').html(metadataHtml);
                        } else {
                            $section.append('<div class="hhlc-linen-metadata">' + metadataHtml + '</div>');
                        }

                        // Update room list badge to show saved state
                        updateRoomListBadge();

                        // Show success message
                        $status.html('<span class="notice notice-success">Count submitted successfully</span>');
                        setTimeout(function() {
                            $status.empty();
                        }, 3000);

                        // Trigger custom event for other components
                        $(document).trigger('hhdl:linen-count-submitted', {
                            location_id: locationId,
                            room_id: roomId,
                            date: date,
                            counts: counts
                        });

                    } else {
                        // Show error
                        $status.html('<span class="notice notice-error">' +
                                   (response.data || 'Failed to submit count') + '</span>');
                        $button.prop('disabled', false).text('Submit Count');
                    }
                },
                error: function(xhr, status, error) {
                    $status.html('<span class="notice notice-error">Network error. Please try again.</span>');
                    $button.prop('disabled', false).text('Submit Count');
                    console.error('Linen count submission error:', error);
                }
            });
        });
    }

    /**
     * Initialize edit handler
     */
    function initLinenEditHandler() {
        $(document).on('click', '.hhlc-edit-linen-count', function(e) {
            e.preventDefault();

            const $button = $(this);
            const $section = $button.closest('.hhlc-linen-controls');
            const $status = $section.find('.hhlc-linen-status');

            // Get room info
            const locationId = $section.data('location');
            const roomId = $section.data('room');
            const date = $section.data('date');

            // Show loading state
            $button.prop('disabled', true).text('Unlocking...');

            // Unlock via AJAX
            $.ajax({
                url: hhlcAjax.ajax_url,
                type: 'POST',
                data: {
                    action: 'hhlc_unlock_linen_count',
                    nonce: hhlcAjax.nonce,
                    location_id: locationId,
                    room_id: roomId,
                    date: date
                },
                success: function(response) {
                    if (response.success) {
                        // Update UI to unlocked state
                        $section.removeClass('locked');
                        $section.find('button.linen-count-up, button.linen-count-down').prop('disabled', false);
                        $section.find('.linen-count-value').prop('readonly', false);

                        // Update state to unlocked
                        currentLinenState.isLocked = false;

                        // Change button back to submit mode
                        $button.removeClass('hhlc-edit-linen-count')
                               .addClass('button-primary hhlc-submit-linen-count')
                               .prop('disabled', false)
                               .text('Submit Count');

                        // Update room list badge to show unsaved state
                        updateRoomListBadge();

                        // Show notification
                        $status.html('<span class="notice notice-info">Count unlocked for editing</span>');
                        setTimeout(function() {
                            $status.empty();
                        }, 2000);

                    } else {
                        $status.html('<span class="notice notice-error">' +
                                   (response.data || 'Failed to unlock count') + '</span>');
                        $button.prop('disabled', false).text('Edit');
                    }
                },
                error: function(xhr, status, error) {
                    $status.html('<span class="notice notice-error">Network error. Please try again.</span>');
                    $button.prop('disabled', false).text('Edit');
                    console.error('Linen count unlock error:', error);
                }
            });
        });
    }

    /**
     * Initialize heartbeat for real-time updates
     */
    function initLinenHeartbeat() {
        // Hook into WordPress heartbeat
        $(document).on('heartbeat-send', function(event, data) {
            // Send heartbeat if we have state (either room list is visible OR modal is open)
            if (currentLinenState.location_id && currentLinenState.date) {
                const heartbeatData = {
                    location_id: currentLinenState.location_id,
                    last_check: lastLinenCheckTimestamp || new Date().toISOString(),
                    viewing_date: currentLinenState.date,
                    current_room: currentLinenState.room_id || null,
                    modal_open: $('#hhdl-modal').is(':visible') || false
                };

                console.log('HHLC: Sending heartbeat data', heartbeatData);
                data.hhlc_linen_monitor = heartbeatData;
            } else {
                console.log('HHLC: Not sending heartbeat - missing state', {
                    has_location: !!currentLinenState.location_id,
                    has_date: !!currentLinenState.date
                });
            }
        });

        // Handle heartbeat response
        $(document).on('heartbeat-tick', function(event, data) {
            console.log('HHLC: Heartbeat tick received', data);
            if (data.hhlc_linen_updates) {
                console.log('HHLC: Processing linen updates', data.hhlc_linen_updates);
                processLinenUpdates(data.hhlc_linen_updates);
            } else {
                console.log('HHLC: No linen updates in heartbeat response');
            }
        });
    }

    let lastLinenCheckTimestamp = new Date().toISOString();

    /**
     * Process linen count updates from heartbeat
     */
    function processLinenUpdates(updates) {
        if (!updates.updates || !updates.updates.length) {
            console.log('HHLC: No updates to process');
            return;
        }

        console.log('HHLC: Processing', updates.updates.length, 'linen count updates');

        // Check if modal is open
        const isModalOpen = $('#hhdl-modal').is(':visible');
        const currentOpenRoom = currentLinenState.room_id;

        console.log('HHLC: Modal state - open:', isModalOpen, 'current room:', currentOpenRoom);

        updates.updates.forEach(function(update) {
            console.log('HHLC: Processing update for room', update.room_id, 'item', update.linen_item_id, 'count', update.count);

            // Check if this update is for a room currently being viewed in the modal
            const $modalSection = $('.hhlc-linen-controls[data-room="' + update.room_id + '"]');
            // Convert both to strings for comparison to avoid type mismatch
            const isRelevantRoom = isModalOpen && String(currentOpenRoom) === String(update.room_id);

            console.log('HHLC: Update relevance - modal section found:', $modalSection.length > 0, 'is relevant room:', isRelevantRoom, 'comparing:', String(currentOpenRoom), '===', String(update.room_id));

            if ($modalSection.length && isRelevantRoom) {
                console.log('HHLC: Applying update to modal');
                // Update the count only if the modal is open for this specific room
                const $item = $modalSection.find('.hhlc-linen-item[data-item-id="' + update.linen_item_id + '"]');
                if ($item.length) {
                    const $input = $item.find('.linen-count-value');
                    const currentInputValue = parseInt($input.val()) || 0;

                    console.log('HHLC: Current value:', currentInputValue, 'New value:', update.count);

                    // Only update if value has changed to avoid overwriting user's current edits
                    if (currentInputValue !== update.count) {
                        console.log('HHLC: Updating input value from', currentInputValue, 'to', update.count);

                        // Check if this is a conflict - user had unsaved changes
                        const hadUnsavedChanges = $item.hasClass('changed');
                        const originalValue = parseInt($input.data('original')) || 0;

                        $input.val(update.count);
                        $input.data('original', update.count);

                        // Update state to keep badge in sync
                        currentLinenState.currentCounts[update.linen_item_id] = update.count;
                        currentLinenState.originalCounts[update.linen_item_id] = update.count;

                        // Update the room list badge
                        updateRoomListBadge();

                        // Add visual emphasis for heartbeat update
                        $item.addClass('heartbeat-updated');

                        // Get item shortcode for readable notification
                        const itemShortcode = $item.find('.linen-shortcode').text() || update.linen_item_id;

                        // Show toast notification with who updated and what changed
                        const updaterName = update.last_updated_by_name || update.submitted_by_name;

                        // Detect conflict: user had unsaved changes different from the update
                        if (hadUnsavedChanges && currentInputValue !== update.count) {
                            console.log('HHLC: CONFLICT DETECTED - User had unsaved changes that were overwritten');
                            showToast(
                                '⚠️ CONFLICT: ' + itemShortcode + ' was updated by ' + updaterName + ' to ' + update.count + ' (you had ' + currentInputValue + ')',
                                'warning'
                            );
                        } else {
                            // Normal update notification
                            console.log('HHLC: Showing toast notification for update by:', updaterName);
                            showToast(
                                itemShortcode + ' updated by ' + updaterName + ' (was ' + originalValue + ', now ' + update.count + ')',
                                'info'
                            );
                        }

                        // Remove emphasis after 3 seconds
                        setTimeout(function() {
                            $item.removeClass('heartbeat-updated');
                        }, 3000);

                        // If locked, update immediately; if unlocked, just update the original value
                        if ($modalSection.hasClass('locked')) {
                            $item.removeClass('changed');
                        } else {
                            // Remove changed class since we've updated to the new value
                            $item.removeClass('changed');
                        }

                        console.log('HHLC: Value updated successfully with visual emphasis');
                    } else {
                        console.log('HHLC: Value unchanged, skipping update');
                    }
                } else {
                    console.log('HHLC: Item element not found for', update.linen_item_id);
                }

                // Update metadata
                let metadataHtml = '<small>Submitted by ' + update.submitted_by_name +
                                 ' at ' + formatTime(update.submitted_at);
                if (update.last_updated_by) {
                    metadataHtml += '<br>Last edited by ' + update.last_updated_by_name +
                                  ' at ' + formatTime(update.last_updated_at);
                }
                metadataHtml += '</small>';

                if ($modalSection.find('.hhlc-linen-metadata').length) {
                    $modalSection.find('.hhlc-linen-metadata').html(metadataHtml);
                } else {
                    $modalSection.append('<div class="hhlc-linen-metadata">' + metadataHtml + '</div>');
                }
            } else {
                console.log('HHLC: Skipping update - not for current room or modal not visible');
            }
        });

        // Update last check timestamp
        lastLinenCheckTimestamp = updates.timestamp;
    }

    /**
     * Format time string
     */
    function formatTime(datetime) {
        const date = new Date(datetime);
        return date.toLocaleTimeString('en-US', {
            hour: '2-digit',
            minute: '2-digit',
            hour12: false
        });
    }

    /**
     * Show toast notification (uses existing daily-list toast function)
     */
    function showToast(message, type) {
        console.log('HHLC showToast: Called with message:', message, 'type:', type);
        console.log('HHLC showToast: window.showToast exists:', typeof window.showToast !== 'undefined');

        if (typeof window.showToast === 'function') {
            console.log('HHLC showToast: Using window.showToast');
            window.showToast(message, type);
        } else {
            console.log('HHLC showToast: Using fallback');
            // Fallback if daily-list.js toast isn't available
            type = type || 'info';
            const $toast = $('<div class="hhdl-toast hhdl-toast-' + type + '">' + message + '</div>');
            $('body').append($toast);

            console.log('HHLC showToast: Toast element created and appended');

            // Trigger animation
            setTimeout(function() {
                $toast.addClass('hhdl-toast-show');
                console.log('HHLC showToast: Added show class');
            }, 10);

            // Auto-hide after 3 seconds
            setTimeout(function() {
                $toast.removeClass('hhdl-toast-show');
                setTimeout(function() {
                    $toast.remove();
                    console.log('HHLC showToast: Toast removed');
                }, 300);
            }, 3000);
        }
    }

    /**
     * Initialize state from linen controls element
     */
    function initializeLinenState($linenSection) {
        if (!$linenSection || !$linenSection.length) {
            console.log('HHLC: No linen section provided to initializeLinenState');
            return false;
        }

        // Check if we already initialized this section
        if ($linenSection.data('hhlc-initialized')) {
            console.log('HHLC: Linen section already initialized, skipping');
            return false;
        }

        // Update state
        currentLinenState.location_id = $linenSection.data('location');
        currentLinenState.room_id = $linenSection.data('room');
        currentLinenState.date = $linenSection.data('date');
        currentLinenState.isLocked = $linenSection.hasClass('locked');

        console.log('HHLC: State initialized from linen controls', currentLinenState);

        // Store original values
        $linenSection.find('.linen-count-value').each(function() {
            const $item = $(this).closest('.hhlc-linen-item');
            const itemId = $item.data('item-id');
            const value = parseInt($(this).val()) || 0;
            $(this).data('original', value);
            currentLinenState.originalCounts[itemId] = value;
            currentLinenState.currentCounts[itemId] = value;
        });
        console.log('HHLC: Stored', Object.keys(currentLinenState.originalCounts).length, 'original count values');

        // Mark as initialized
        $linenSection.data('hhlc-initialized', true);
        return true;
    }

    /**
     * Watch for linen controls appearing in DOM
     */
    function watchForLinenControls() {
        // Check if linen controls already exist
        const $existing = $('.hhlc-linen-controls:not([data-hhlc-initialized])');
        if ($existing.length) {
            console.log('HHLC: Found existing linen controls on page load');
            initializeLinenState($existing.first());
        }

        // Watch for new linen controls being added to DOM (e.g., when modal opens)
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.addedNodes.length) {
                    mutation.addedNodes.forEach(function(node) {
                        if (node.nodeType === 1) { // Element node
                            // Check if the added node is or contains linen controls
                            let $linenControls = null;
                            if ($(node).hasClass('hhlc-linen-controls')) {
                                $linenControls = $(node);
                            } else {
                                $linenControls = $(node).find('.hhlc-linen-controls');
                            }

                            if ($linenControls && $linenControls.length && !$linenControls.data('hhlc-initialized')) {
                                console.log('HHLC: Detected new linen controls added to DOM');
                                initializeLinenState($linenControls.first());
                            }
                        }
                    });
                }
            });
        });

        // Start observing the document body for changes
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });

        console.log('HHLC: MutationObserver started, watching for linen controls');
    }

    /**
     * Load linen counts when modal opens (fallback for custom event if it exists)
     */
    $(document).on('hhdl:modal-opened', function(event, data) {
        console.log('HHLC: hhdl:modal-opened event received', data);
        const $linenSection = $('.hhlc-linen-controls:not([data-hhlc-initialized])');
        console.log('HHLC: Found uninitialized linen controls:', $linenSection.length);

        if ($linenSection.length) {
            initializeLinenState($linenSection.first());
        }
    });

    /**
     * Clear state when modal closes
     */
    $(document).on('hhdl:modal-closed', function() {
        currentLinenState = {
            location_id: null,
            room_id: null,
            date: null,
            isLocked: false,
            originalCounts: {},
            currentCounts: {}
        };
    });

    // Initialize on document ready
    $(document).ready(function() {
        console.log('HHLC: Document ready, checking for room list');
        if ($('#hhdl-room-list').length) {
            console.log('HHLC: Room list found, initializing linen count');
            initLinenCount();
        } else {
            console.log('HHLC: Room list not found, will initialize on modal open');
            // Initialize anyway for modal content
            initLinenCount();
        }

        // Start watching for linen controls appearing in DOM
        watchForLinenControls();
    });

    // Export for external use
    window.HHLC_Linen = {
        init: initLinenCount,
        getCurrentState: function() { return currentLinenState; },
        submitCount: function() { $('.hhlc-submit-linen-count').click(); }
    };

})(jQuery);