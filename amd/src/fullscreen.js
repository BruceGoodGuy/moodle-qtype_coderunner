/**
 * This file is part of Moodle - http:moodle.org/
 *
 * Moodle is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Moodle is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Moodle.  If not, see <http:www.gnu.org/licenses/>.
 */

/**
 * JavaScript to handle full screen.
 *
 * @module qtype_coderunner/fullscreen
 * @copyright 2023 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Notification from 'core/notification';
import Templates from 'core/templates';

/**
 * Fullscreen Editor constructor designed to handle every fullscreen feature.
 *
 * @param {string} questionId id of the outer question div.
 * @param {string} fieldId The id of answer field.
 * @param {string} uiPlugin The input UI type.
 * @constructor
 */
function FullscreenEditor(questionId, fieldId, uiPlugin) {
    // Set-up.
    const questionDiv = document.getElementById(questionId);
    const editorSize = {};
    let fullScreenButton;
    let exitFullscreenButton;
    const editorWrapper = getEditorSelector(fieldId, uiPlugin);
    // Load fullscreen/exit fullscreen button with icon.
    Templates.renderForPromise('qtype_coderunner/screenmode_button', {}).then(({html}) => {
        const screenModeButton = Templates.appendNodeContents(editorWrapper, html, '')[0];
        fullScreenButton = screenModeButton.querySelector('.button-fullscreen');
        exitFullscreenButton = screenModeButton.querySelector('.button-exit-fullscreen');
        // In Firefox, the resize icon is larger than in Chrome,
        // so we need to add more gap between the full-screen/exit fullscreen button and the resize icon.
        if (document.documentElement.mozRequestFullScreen) {
            fullScreenButton.style.right = '12px';
            exitFullscreenButton.style.right = '12px';
        }
        // When load successfully, show the fullscreen button.
        fullScreenButton.classList.remove('d-none');
        // Attach an event to the fullscreen/exit-fullscreen button.
        attachEvent(uiPlugin);
    });

    /**
     * Attach event.
     *
     * @param {string} uiPlugin The id of the ui plugin.
     */
    function attachEvent(uiPlugin) {

        // Attach an event to the exit fullscreen button.
        exitFullscreenButton.addEventListener('click', e => {
            e.preventDefault();
            document.exitFullscreen();
        });

        // Attach event to fullscreen button.
        fullScreenButton.addEventListener('click', (e) => {
            e.preventDefault();
            getInitialSize(uiPlugin);
            fullScreenButton.classList.add('d-none');
            // Handle fullscreen event.
            editorWrapper.addEventListener('fullscreenchange', () => {
                // When exit fullscreen.
                if (document.fullscreenElement === null) {
                    setDefaultSize(uiPlugin);
                    exitFullscreenButton.classList.add('d-none');
                    fullScreenButton.classList.remove('d-none');
                } else {
                    exitFullscreenButton.classList.remove('d-none');
                }
            });

            editorWrapper.requestFullscreen().catch(Notification.exception);
        });
    }

    /**
     * Each ui plugin has different editor element.
     * This function designed to get the editor element for the fullscreen zone.
     *
     * @param {string} fieldId ID of the textarea.
     * @param {string} uiPlugin The id of the ui plugin.
     * @return {Element} Editor element.
     */
    function getEditorSelector(fieldId, uiPlugin) {
        let editorElement;
        // If the editor is ace or ace_gapfiller then the editor element is the wrapper.
        if (getUIPluginType(uiPlugin) === 'ace') {
            editorElement = document.getElementById(`${fieldId}_wrapper`);
        }

        return editorElement;
    }

    /**
     * Get original height of the editor.
     *
     * @param {string} uiPlugin The type of the ui plugin.
     */
    function getInitialSize(uiPlugin) {
        if (getUIPluginType(uiPlugin) === 'ace') {
            editorSize.wrapper = editorWrapper.style.minHeight;
            editorSize.heightWraper = editorWrapper.style.height;
            editorSize.editor = questionDiv.querySelector('.ace_editor').style.height;
            editorSize.content = questionDiv.querySelector('.ace_content').style.height;
        }
    }

    /**
     * Set height of the editor.
     *
     * @param {string} uiPlugin The type of the ui plugin.
     */
    function setDefaultSize(uiPlugin) {
        if (getUIPluginType(uiPlugin) === 'ace') {
            editorWrapper.style.minHeight = editorSize.wrapper;
            editorWrapper.style.height = editorSize.heightWraper;
            questionDiv.querySelector('.ace_editor').style.height = editorSize.editor;
            questionDiv.querySelector('.ace_content').style.height = editorSize.content;
        }
    }

    /**
     * Get the ui plugin type.
     * If additional ui plugin is added, it should be defined here.
     *
     * @param {string} uiPlugin The type of the ui plugin.
     * @returns {string} Type of the ui plugin.
     */
    function getUIPluginType(uiPlugin) {
        if (uiPlugin === 'ace' || uiPlugin === 'ace_gapfiller') {
           return 'ace';
        }

        return 'default';
    }
}

/**
 * Initialize the full screen.
 *
 * @param {string} questionId id of the outer question div.
 * @param {string} fieldId The id of answer field.
 * @param {string} uiPlugin The input UI type.
 */
export const init = (questionId, fieldId, uiPlugin) => {
    new FullscreenEditor(questionId, fieldId, uiPlugin);
};
