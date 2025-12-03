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

    // Create debounced version of auto-save (wait 800ms after last change)
    const debouncedAutoSave = debounce(autoSaveLinenCount, 800);

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

                        // Change button back to submit mode
                        $button.removeClass('hhlc-edit-linen-count')
                               .addClass('button-primary hhlc-submit-linen-count')
                               .prop('disabled', false)
                               .text('Submit Count');

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
            // Only send if we're viewing the daily list
            if ($('#hhdl-room-list').length && currentLinenState.location_id) {
                data.hhlc_linen_monitor = {
                    location_id: currentLinenState.location_id,
                    last_check: lastLinenCheckTimestamp || new Date().toISOString(),
                    viewing_date: currentLinenState.date || (typeof currentDate !== 'undefined' ? currentDate : ''),
                    current_room: currentLinenState.room_id || null,
                    modal_open: $('#hhdl-modal').is(':visible') || false
                };
            }
        });

        // Handle heartbeat response
        $(document).on('heartbeat-tick', function(event, data) {
            if (data.hhlc_linen_updates) {
                processLinenUpdates(data.hhlc_linen_updates);
            }
        });
    }

    let lastLinenCheckTimestamp = new Date().toISOString();

    /**
     * Process linen count updates from heartbeat
     */
    function processLinenUpdates(updates) {
        if (!updates.updates || !updates.updates.length) {
            return;
        }

        // Check if modal is open
        const isModalOpen = $('#hhdl-modal').is(':visible');
        const currentOpenRoom = currentLinenState.room_id;

        updates.updates.forEach(function(update) {
            // Check if this update is for a room currently being viewed in the modal
            const $modalSection = $('.hhlc-linen-controls[data-room="' + update.room_id + '"]');
            const isRelevantRoom = isModalOpen && currentOpenRoom === update.room_id;

            if ($modalSection.length && isRelevantRoom) {
                // Update the count only if the modal is open for this specific room
                const $item = $modalSection.find('.hhlc-linen-item[data-item-id="' + update.linen_item_id + '"]');
                if ($item.length) {
                    const $input = $item.find('.linen-count-value');
                    const currentInputValue = parseInt($input.val()) || 0;

                    // Only update if value has changed to avoid overwriting user's current edits
                    if (currentInputValue !== update.count) {
                        $input.val(update.count);
                        $input.data('original', update.count);

                        // If locked, update immediately; if unlocked, just update the original value
                        if ($modalSection.hasClass('locked')) {
                            $item.removeClass('changed');
                        }
                    }
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

                // Show notification ONLY if:
                // 1. Modal is open for this room
                // 2. Another user made the update
                // 3. Update was recent (last_updated_by exists means it was edited, not just submitted)
                if (update.last_updated_by && update.last_updated_by != hhlcAjax.user_id) {
                    const updaterName = update.last_updated_by_name || update.submitted_by_name;
                    showToast('Linen count updated by ' + updaterName + ' for room ' + update.room_id, 'info');
                } else if (!update.last_updated_by && update.submitted_by != hhlcAjax.user_id) {
                    showToast('Linen count submitted by ' + update.submitted_by_name + ' for room ' + update.room_id, 'info');
                }
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
        if (window.showToast) {
            window.showToast(message, type);
        } else {
            // Fallback if daily-list.js toast isn't available
            const $toast = $('<div class="hhdl-toast hhdl-toast-' + type + '">' + message + '</div>');
            $('body').append($toast);
            setTimeout(function() {
                $toast.addClass('show');
            }, 100);
            setTimeout(function() {
                $toast.removeClass('show');
                setTimeout(function() {
                    $toast.remove();
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