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
 * Copyright (c) 2016 (original work) Open Assessment Technologies SA ;
 */
/**
 * @author Jean-Sébastien Conan <jean-sebastien.conan@vesperiagroup.com>
 */
define([
    'taoTests/runner/plugin'
], function (pluginFactory) {
    'use strict';

    /**
     * Creates the testState plugin.
     * Handle particular states of the assessment test
     */
    return pluginFactory({

        name: 'testState',

        /**
         * Initializes the plugin (called during runner's init)
         */
        init: function init() {
            var testRunner = this.getTestRunner();

            testRunner.getProxy()
                // middleware invoked on every requests
                .use(function qtiFilter(req, res, next) {
                    var data = res && res.data;

                    // test has been closed/suspended => redirect to the index page after message acknowledge
                    if (data && data.type && data.type === 'TestState') {
                        if(!testRunner.getState('ready')){
                            //if we open an inconsistent test (should never happen) just leave
                            testRunner.trigger('destroy');
                        } else {
                            testRunner.trigger('leave', data);
                        }
                        // break the chain to avoid uncaught exception in promise...
                        // this will lead to unresolved promise, but the browser will be redirected soon!
                        return;
                    }
                    next();
                })

                // immediate handling of proctor's actions
                .channel('teststate', function (data) {
                    if (data && ('close' === data.type || 'pause' === data.type)) {
                        testRunner.getProxy().getCommunicator()
                            .then(function(communicator) {
                                communicator.close();
                            });
                        testRunner.trigger('leave', data);
                    }
                });
        }
    });
});
