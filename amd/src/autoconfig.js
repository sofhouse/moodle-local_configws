import ModalForm from 'core_form/modalform';
import Config from 'core/config';
import {get_string as getString} from 'core/str';

export const init = (formClass) => {
    let button = document.querySelector('[data-action="autoconfig-form"]');
    button.addEventListener('click', async (e) => {
        e.preventDefault();
        let target = e.currentTarget;
        renderModal(formClass, false, false,target);
    });
};

/**
 * Render the modal form.
 *
 * @param {string} formClass
 * @param {int} user
 * @param {int} webservice
 * @param {object} target
 * @param {object} jsondata
 * @returns {void}
*/
async function renderModal(formClass, user = false, webservice = false, target = false, jsondata = false) {
    let args = {};
    if (jsondata !== false) {
        args = jsondata;
        args.userid = user;
        args.webservice = webservice;
        if (args.functions !== undefined) {
            args.functions = args.functions.join(',');
        }
        args.isjson = 1;
    } else {
        args = {
            user: user,
            webservice: webservice
        };
    }
    const form = new ModalForm({
        formClass,
        args: args,
        modalConfig: {
            title: getString('autoconfig', 'local_configws'),
        },
        saveButtonText: 'Save',
        returnFocus: target
    });
    form.addEventListener(form.events.FORM_SUBMITTED, (e) => {
        const response = e.detail;
        let redirect = `${Config.wwwroot}/local/configws/autoconfig.php?`;
        let first = false;
        for (let key in response) {
            if (first) {
                first = false;
            } else {
                redirect = redirect + '&';
            }
            redirect = redirect + `${key}=${response[key]}`;
        }

        window.location.assign(redirect);
    });
    await form.show();

    let bodymodal = form.modal.getRoot().find('.modal-body')[0];

    // Create a new MutationObserver
    const observer = new MutationObserver((mutationsList, observer) => {
        // Check if a select element is added to the body
        const selectAdded = mutationsList.some((mutation) => {
            return mutation.addedNodes && Array.from(mutation.addedNodes).some(() => {
                return document.querySelector('.modal-body select[name="user"]');
            });
        });
        if (selectAdded) {
            observer.disconnect();
            // Select the event
            const select = document.querySelector('.modal-body select[name="user"]');
            const webservice = document.querySelector('.modal-body select[name="webservice"]');
            select.addEventListener('change', (e) => {
                form.modal.destroy();
                if (e.target.value === '') {
                    return;
                }
                renderModal(formClass, e.target.value, webservice.value);
            });
        }
    });

    // Start observing the body for mutations
    observer.observe(bodymodal, { childList: true, subtree: true });

    const wsobserver = new MutationObserver((mutationsList, observer) => {
        // Check if a select element is added to the body
        const selectAdded = mutationsList.some((mutation) => {
            return mutation.addedNodes && Array.from(mutation.addedNodes).some(() => {
                return document.querySelector('.modal-body select[name="webservice"]');
            });
        });
        if (selectAdded) {

            observer.disconnect();
            // Add the save json button functionality.
            let savejsonbutton = document.querySelector('.modal-body button[name="jsonsave"]');
            savejsonbutton.addEventListener('click', () => {
                let downloadlink = '/pluginfile.php/10/local_configws/download/' + webservice + '/?userid=' + user;
                window.location.assign(downloadlink);
            });

            let loadjsonbutton = document.querySelector('.modal-body button[name="jsonload"]');
            loadjsonbutton.addEventListener('click', () => {
                const input = document.createElement('input');
                input.type = 'file';
                input.accept = 'application/json';
                input.addEventListener('change', (event) => {
                    const file = event.target.files[0];
                    if (file) {
                        const reader = new FileReader();
                        reader.onload = async (e) => {
                            const jsonContent = e.target.result;
                            const data = JSON.parse(jsonContent);
                            if (!validateJson(data)) {
                                alert('Invalid JSON file');
                                return;
                            }
                            form.modal.destroy();
                            await renderModal(formClass, data.userid, 'new', target, data);
                        };
                        reader.readAsText(file);
                    }
                });
                input.click();
            });
            // Select the event
            const select = document.querySelector('.modal-body select[name="webservice"]');
            const userid = document.querySelector('.modal-body select[name="user"]').value;
            select.addEventListener('change', (e) => {
                if (e.target.value === 'new' || e.target.value === '0') {
                    return;
                }
                form.modal.destroy();
                renderModal(formClass, userid, e.target.value, target);
            });
        }
    }
    );

    // Start observing the body for mutations
    wsobserver.observe(bodymodal, { childList: true, subtree: true });
}

/**
 * Validate json file.
 * @param {object} data
 */
function validateJson(data) {
    if (typeof data !== 'object') {
        return false;
    }
    if (data === null) {
        return false;
    }
    if (data.userid === undefined) {
        return false;
    }
    if (data.name === undefined) {
        return false;
    }

    return true;
}
