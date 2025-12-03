function openConfirmModal(formElement, bookId, bookTitle) {
    document.getElementById('modalMessage').innerHTML = `Reserve <b>${bookTitle}</b>?`;
    document.getElementById('modalBookId').value = bookId;
    document.getElementById('modalBookTitle').value = bookTitle;
    document.getElementById('modalConfirmBtn').onclick = function() {
        sessionStorage.setItem('scrollPosition', window.scrollY);
        document.getElementById('modalSubmissionForm').submit();
    };
    document.getElementById('confirmActionModal').style.display = 'flex';
    return false;
}