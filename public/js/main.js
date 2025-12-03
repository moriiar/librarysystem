/* public/js/main.js */

/* 1. Global Sidebar Logic */
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar-menu');
    const mainContent = document.getElementById('main-content-wrapper') || document.getElementById('main-content-area');

    if(sidebar && mainContent) {
        sidebar.classList.toggle('active');
        mainContent.classList.toggle('pushed');
        localStorage.setItem('sidebarState', sidebar.classList.contains('active') ? 'expanded' : 'collapsed');
    }
}

/* 2. Global Modal Helpers */
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) modal.style.display = 'flex';
}
function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) modal.style.display = 'none';
}
window.onclick = function (event) {
    if (event.target.classList.contains('modal')) event.target.style.display = 'none';
}

/* 3. Logic for "Student Reserve Book" Modal */
function openConfirmModal(formElement, bookId, bookTitle) {
    const modalMessage = document.getElementById('modalMessage');
    const submissionForm = document.getElementById('modalSubmissionForm');

    // Set data into the hidden form in the modal
    document.getElementById('modalBookId').value = bookId;
    document.getElementById('modalBookTitle').value = bookTitle;
    
    // Set Message
    if(modalMessage) {
        modalMessage.innerHTML = `Do you want to reserve <b>${bookTitle}</b>?<br><small>Request will be sent for approval.</small>`;
    }

    // Handle Confirm Click
    const confirmBtn = document.getElementById('modalConfirmBtn');
    if(confirmBtn) {
        confirmBtn.onclick = function () {
            // Save scroll position
            sessionStorage.setItem('scrollPosition', window.scrollY);
            submissionForm.submit();
        };
    }
    
    openModal('confirmActionModal');
    return false; // Prevent default form submission
}

/* 4. Logic for "Cancel Reservation" Modal */
function openCancelModal(reservationId, bookTitle) {
    const msg = document.getElementById('modalMessage');
    const input = document.getElementById('modalReservationId');
    
    if(msg) msg.innerHTML = `Are you sure you want to cancel reservation for <b>${bookTitle}</b>?`;
    if(input) input.value = reservationId;
    
    openModal('cancelReservationModal');
}

/* 5. Logic for "Returns & Clearance" Calculation */
function updateFormLogic() {
    const processType = document.getElementById('processType');
    if(!processType) return; // Exit if not on this page

    const condition = document.getElementById('conditionSelect').value;
    const rawPrice = parseFloat(document.getElementById('raw_book_price').value);
    const rawInitialPenalty = parseFloat(document.getElementById('raw_initial_penalty').value);
    
    const feeBox = document.getElementById('feeBox');
    const displayFee = document.getElementById('displayFee');
    const inputPenalty = document.getElementById('inputPenalty');
    const submitBtn = document.getElementById('submitBtn');
    const conditionGroup = document.getElementById('conditionGroup');
    const paymentGroup = document.getElementById('paymentGroup');

    let finalFee = 0.00;

    if (processType.value === 'lost') {
        finalFee = rawPrice;
        submitBtn.innerText = "Confirm Mark as Lost";
        submitBtn.className = "submit-btn btn-lost";
        conditionGroup.style.display = 'none';
    } else {
        submitBtn.innerText = "Confirm Return";
        submitBtn.className = "submit-btn btn-return";
        conditionGroup.style.display = 'block';
        finalFee = rawInitialPenalty;
        if (condition === 'Major Damage') finalFee = rawPrice;
    }

    if(displayFee) displayFee.innerText = finalFee.toFixed(2);
    if(inputPenalty) inputPenalty.value = finalFee.toFixed(2);

    const showFee = finalFee > 0;
    if(feeBox) feeBox.style.display = showFee ? 'block' : 'none';
    if(paymentGroup) paymentGroup.style.display = showFee ? 'block' : 'none';
}


/* 6. On Load Events */
document.addEventListener('DOMContentLoaded', () => {
    // Restore Sidebar
    const savedState = localStorage.getItem('sidebarState');
    const sidebar = document.getElementById('sidebar-menu');
    const mainContent = document.getElementById('main-content-wrapper') || document.getElementById('main-content-area');
    
    if (savedState === 'expanded' && sidebar) {
        sidebar.classList.add('active');
        if(mainContent) mainContent.classList.add('pushed');
    }

    // Scroll Restore (for student page)
    const savedScroll = sessionStorage.getItem('scrollPosition');
    if (savedScroll) {
        window.scrollTo(0, parseInt(savedScroll));
        sessionStorage.removeItem('scrollPosition');
    }

    // Notification Auto-Hide
    const notification = document.getElementById('statusNotification');
    if (notification) {
        setTimeout(() => { notification.classList.add('hidden'); }, 3000);
        if (window.history.replaceState) {
            const url = new URL(window.location);
            url.searchParams.delete('msg');
            url.searchParams.delete('type');
            window.history.replaceState({}, '', url);
        }
    }

    // Search Input Clear Button
    const searchInput = document.getElementById('search-input-field');
    const clearBtn = document.querySelector('.clear-btn');
    if (searchInput && clearBtn) {
        searchInput.addEventListener('input', function () {
            clearBtn.style.display = this.value.length > 0 ? 'block' : 'none';
        });
    }
});