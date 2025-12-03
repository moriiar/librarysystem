function updateFormLogic() {
    const processType = document.getElementById('processType').value;
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

    if (processType === 'lost') {
        // Lost = Full Price regardless of due date
        finalFee = rawPrice;
        submitBtn.innerText = "Confirm Mark as Lost";
        submitBtn.className = "submit-btn btn-lost";
        conditionGroup.style.display = 'none'; // No condition if lost
    } else {
        // Return
        submitBtn.innerText = "Confirm Return";
        submitBtn.className = "submit-btn btn-return";
        conditionGroup.style.display = 'block';

        // Base penalty (Overdue)
        finalFee = rawInitialPenalty;

        // Add Major Damage Fee (Full Price replacement)
        if (condition === 'Major Damage') {
            finalFee = rawPrice;
        }
    }

    // Update UI
    displayFee.innerText = finalFee.toFixed(2);
    inputPenalty.value = finalFee.toFixed(2);

    if (finalFee > 0) {
        feeBox.style.display = 'block';
        paymentGroup.style.display = 'block';
    } else {
        feeBox.style.display = 'none';
        paymentGroup.style.display = 'none';
    }
}