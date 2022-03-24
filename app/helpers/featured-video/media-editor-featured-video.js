(function($, _){
    /**
     * @namespace wp.media.featuredVideo
     * @memberOf wp.media
     */
    wp.media.featuredVideo = {
        /**
         * Get the featured image post ID
         *
         * @return {wp.media.view.settings.post.featuredVideoId|number}
         */
        get: function() {
            return wp.media.view.settings.post.featuredVideoId;
        },
        /**
         * Sets the featured image ID property and sets the HTML in the post meta box to the new featured image.
         *
         * @param {number} id The post ID of the featured image, or -1 to unset it.
         */
        set: function( id ) {
            var settings = wp.media.view.settings;

            settings.post.featuredVideoId = id;

            wp.media.post( 'get_post_video_html', {
                post_id: settings.post.id,
                video_id: settings.post.featuredVideoId,
                _wpnonce: settings.post.nonce
            }).done( function( html ) {
                if ( '0' === html ) {
                    window.alert( wp.i18n.__( 'Could not set that as the video. Try a different attachment.' ) );
                    return;
                }
                $( '.inside', '#postvideodiv' ).html( html );
            });
        },
        /**
         * Remove the featured image id, save the post thumbnail data and
         * set the HTML in the post meta box to no featured image.
         */
        remove: function() {
            wp.media.featuredVideo.set( -1 );
        },
        /**
         * The Featured Image workflow
         *
         * @this wp.media.featuredVideo
         *
         * @return {wp.media.view.MediaFrame.Select} A media workflow.
         */
        frame: function() {
            if ( this._frame ) {
                wp.media.frame = this._frame;
                return this._frame;
            }

            this._frame = frame = wp.media({
                frame: 'select',
                id: 'featured-video',
                title: 'Featured video',
                button: {
                    text: 'Set featured video'
                },
                library: {
                    type: [ 'video' ]
                },
                multiple: false
            });

            this._frame.on('open', function() {
                var selection = frame.state().get('selection');
                if (selection.length === 0) {
                    var selected = wp.media.featuredVideo.get();
                    if (selected) {
                        selection.add(wp.media.attachment(selected));
                    }
                }
            });

            this._frame.on( 'select', function() {
                var selection = frame.state().get('selection').single();
                if ( typeof selection === 'undefined' ) {
                    return;
                }
                wp.media.featuredVideo.set( selection ? selection.id : -1 );
            });

            return this._frame;
        },
        /**
         * Open the content media manager to the 'featured image' tab when
         * the post thumbnail is clicked.
         *
         * Update the featured image id when the 'remove' link is clicked.
         */
        init: function() {
            $('#postvideodiv').on( 'click', '#set-post-video', function( event ) {
                event.preventDefault();
                // Stop propagation to prevent thickbox from activating.
                event.stopPropagation();

                wp.media.featuredVideo.frame().open();
            }).on( 'click', '#remove-post-video', function() {
                wp.media.featuredVideo.remove();
                return false;
            });
        }
    };

    $( wp.media.featuredVideo.init );
}(jQuery, _));
