;(function ( $, window, document, undefined ) {
    
    'use strict';
    
    var funcName = 'thumbRating';
    var defaults = {
        readOnly: false,
        disableAfterRate: false,
        callback: function(){}
    };
    var Function = function( element, options ) {
        this.$el = $(element);
        this.settings = $.extend( {}, defaults, options );
        this._defaults = defaults;
        this._name = funcName;
        this.init();
    };

    var methods = {
        init: function () {
            this.renderMarkup();
            this.addListeners();
        },
        renderMarkup: function () {
            this.$thumbs = this.$el.find('.thumb');
        },
        addListeners: function(){
            if( this.settings.readOnly ){ return; }
            this.$thumbs.on('click', this.handleRating.bind(this));
        },
        handleRating: function(e){
            this.executeCallback( this.$el );
            if(this.settings.disableAfterRate){
                this.$thumbs.off();
                this.$thumbs.unbind();
            }
        },
        executeCallback: function( $el ){
            var callback = this.settings.callback;
            callback( $el );
        }
    };

    // Avoid Function.prototype conflicts
    $.extend(Function.prototype, methods);
    $.fn[ funcName ] = function ( options ) {
        return this.each(function() {
            // preventing against multiple instantiations
            if ( !$.data( this, 'plugin_' + funcName ) ) {
                $.data( this, 'plugin_' + funcName, new Function( this, options ) );
            }
        });
    };

})( jQuery, window, document );
