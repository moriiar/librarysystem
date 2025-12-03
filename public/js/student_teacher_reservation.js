function openCancelModal(resId, title) {
    document.getElementById('modalMessage').innerHTML = `Cancel reservation for <b>${title}</b>?`;
    document.getElementById('modalReservationId').value = resId;
    document.getElementById('cancelReservationModal').style.display = 'flex';
}