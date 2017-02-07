
/**
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; under version 2
 * of the License (non-upgradable).
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 *
 * Copyright (c) 2017 (original work) Open Assessment Technologies SA;
 */

/**
 * Answer Eliminator Plugin
 *
 * While the platform's answer eliminator works on a per-item base, this variation allows
 * answer elimination on a test level. Essentially it allows to add a class 'eliminable'
 * to a choice interaction, from there the aforementioned item-based behaviour and styling takes
 * over.
 *
 * Alternative styling will be on a per customer basis and should always be published as a recipe
 * in the theme-toolkit.
 *
 * @author Dieter Raber <dieter@taotesting.com>
 */
define([
    'jquery',
    'lodash',
    'i18n',
    'ui/hider',
    'util/shortcut',
    'util/namespace',
    'taoTests/runner/plugin',
    'tpl!taoQtiTest/runner/plugins/templates/button'
], function ($, _, __, hider, shortcut, namespaceHelper, pluginFactory, buttonTpl){
    'use strict';

    /**
     * The public name of the plugin
     * @type {String}
     */
    var pluginName = 'eliminator';

    /**
     * The prefix of actions triggered through the event loop
     * @type {String}
     */
    var actionPrefix = 'tool-' + pluginName + '-';


    /**
     * Returns the configured plugin
     */
    return pluginFactory({

        name: pluginName,

        /**
         * Initialize the plugin (called during runner's init)
         */
        init : function init(){
            var self = this;

            var testRunner = this.getTestRunner();
            var $container = testRunner.getAreaBroker().getContentArea().parent();
            var testConfig = testRunner.getTestData().config || {};
            var pluginShortcuts = (testConfig.shortcuts || {})[pluginName] || {};

            //build the control button
            this.$button = $(buttonTpl({
                control : 'eliminator',
                title : __('Eliminate choices'),
                icon : 'eliminate'
            }));

            /**
             * Checks if the plugin is currently available
             * @returns {Boolean}
             */
            function isEnabled() {
                var context = testRunner.getTestContext();
                //to be activated with the special category x-tao-option-eliminator
                return !!context.options.eliminator;
            }

            /**
             * Is plugin activated ? if not, then we hide the plugin
             */
            function togglePlugin() {
                if (isEnabled()) {
                    self.show();
                } else {
                    self.hide();
                }
            }

            //add a new mask each time the button is pressed
            this.$button.on('click', function (e){
                e.preventDefault();
                testRunner.trigger(actionPrefix + 'toggle');
            });

            // handle the plugin's shortcuts
            if (testConfig.allowShortcuts) {
                _.forEach(pluginShortcuts, function (command, key) {
                    shortcut.add(namespaceHelper.namespaceAll(command, pluginName, true), function () {
                        // just fire the action using the event loop
                        testRunner.trigger(actionPrefix + key);
                    }, {
                        avoidInput: true
                    });
                });
            }

            //start disabled
            this.disable();

            //update plugin state based on changes
            testRunner
                .on('loaditem', togglePlugin)
                .on('renderitem', function conditionalInit() {
                    // show button only when in the presence of choice interactions
                    self.$choiceInteractions = $container.find('.qti-choiceInteraction');
                    if(!self.$choiceInteractions.length) {
                        self.hide();
                        return;
                    }
                    if (isEnabled()) {
                        self.show();
                    }
                })
                .on('enabletools renderitem', function (){
                    self.enable();
                })
                .on('disabletools unloaditem', function (){
                    self.disable();
                })
                // commands that controls the plugin
                .on(actionPrefix + 'toggle', function () {
                    if (isEnabled()) {
                        self.$choiceInteractions.toggleClass('eliminable');
                    }
                });
        },

        /**
         * Called during the runner's render phase
         */
        render : function render(){
            this.getAreaBroker().getToolboxArea().append(this.$button);
        },

        /**
         * Called during the runner's destroy phase
         */
        destroy : function destroy(){
            shortcut.remove('.' + pluginName);
            this.$button.remove();
        },

        /**
         * Enable the button
         */
        enable : function enable(){
            this.$button
                .removeProp('disabled')
                .removeClass('disabled');
        },

        /**
         * Disable the button
         */
        disable : function disable(){
            this.$button
                .prop('disabled', true)
                .addClass('disabled');
        },

        /**
         * Show the button
         */
        show : function show(){
            hider.show(this.$button);
        },

        /**
         * Hide the button
         */
        hide : function hide(){
            hider.hide(this.$button);
        }
    });
});
