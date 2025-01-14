import ModalForm from 'core_form/modalform';
import Config from 'core/config';
import {get_string as getString} from 'core/str';

export const init = (formClass) => {
    renderModal(formClass);
};

/**
 * Render the modal form.
 *
 * @param {string} formClass
 * @param {int} user
 * @param {int} webservice
 * @returns {void}
*/
async function renderModal(formClass, user = false, webservice = false) {
    const form = new ModalForm({
        formClass,
        args: {
            user: user,
            webservice: webservice,
        },
        modalConfig: {
            title: getString('autoconfig', 'local_configws'),
        },
        saveButtonText: 'Save',
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
            // Select the event
            const select = document.querySelector('.modal-body select[name="webservice"]');
            const userid = document.querySelector('.modal-body select[name="user"]').value;
            select.addEventListener('change', (e) => {
                if (e.target.value === 'new' || e.target.value === '0') {
                    return;
                }
                form.modal.destroy();
                renderModal(formClass, userid, e.target.value);
            });
        }
    }
    );

    // Start observing the body for mutations
    wsobserver.observe(bodymodal, { childList: true, subtree: true });
}