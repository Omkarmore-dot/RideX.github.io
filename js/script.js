/**
 * RideX - Interactive Client-Side Logic & Dynamic Calculators
 */

document.addEventListener('DOMContentLoaded', function () {
  // 1. Mobile Menu Toggle
  const menuToggle = document.querySelector('.menu-toggle');
  const navLinks = document.querySelector('.nav-links');
  if (menuToggle && navLinks) {
    menuToggle.addEventListener('click', function () {
      navLinks.classList.toggle('show');
    });
  }

  // 2. Tab Navigation (e.g. Hero Booking Widget & Dashboard)
  const tabButtons = document.querySelectorAll('.tab-btn, .dash-tab-btn');
  tabButtons.forEach(btn => {
    btn.addEventListener('click', function () {
      const targetId = this.getAttribute('data-tab');
      const parentContainer = this.closest('.tab-wrapper') || document;
      
      // Deactivate siblings
      const siblingBtns = this.parentElement.querySelectorAll('.tab-btn, .dash-tab-btn');
      siblingBtns.forEach(b => b.classList.remove('active'));
      this.classList.add('active');

      // Show targeted pane
      const contentPanes = parentContainer.querySelectorAll('.tab-content');
      contentPanes.forEach(pane => {
        pane.classList.remove('active');
        if (pane.id === targetId) {
          pane.classList.add('active');
        }
      });
    });
  });

  // 3. Live Ride Booking Fare Calculation
  const vehicleSelect = document.getElementById('ride_vehicle');
  const distanceInput = document.getElementById('distance_km');
  const baseFareDisplay = document.getElementById('base_fare_display');
  const ratePerKmDisplay = document.getElementById('rate_km_display');
  const distanceDisplay = document.getElementById('distance_display');
  const totalFareDisplay = document.getElementById('total_fare_display');
  const totalFareInput = document.getElementById('total_fare_input');

  function calculateRideFare() {
    if (!vehicleSelect || !distanceInput) return;

    const selectedOption = vehicleSelect.options[vehicleSelect.selectedIndex];
    if (!selectedOption || !selectedOption.value) {
      if (baseFareDisplay) baseFareDisplay.textContent = '$0.00';
      if (ratePerKmDisplay) ratePerKmDisplay.textContent = '$0.00';
      if (distanceDisplay) distanceDisplay.textContent = '0 km';
      if (totalFareDisplay) totalFareDisplay.textContent = '$0.00';
      if (totalFareInput) totalFareInput.value = '0';
      return;
    }

    const baseFare = parseFloat(selectedOption.getAttribute('data-base') || 30.00);
    const rateKm = parseFloat(selectedOption.getAttribute('data-rate') || 10.00);
    const distance = parseFloat(distanceInput.value) || 0;

    const totalFare = baseFare + (distance * rateKm);

    if (baseFareDisplay) baseFareDisplay.textContent = '$' + baseFare.toFixed(2);
    if (ratePerKmDisplay) ratePerKmDisplay.textContent = '$' + rateKm.toFixed(2);
    if (distanceDisplay) distanceDisplay.textContent = distance.toFixed(1) + ' km';
    if (totalFareDisplay) totalFareDisplay.textContent = '$' + totalFare.toFixed(2);
    if (totalFareInput) totalFareInput.value = totalFare.toFixed(2);
  }

  if (vehicleSelect && distanceInput) {
    vehicleSelect.addEventListener('change', calculateRideFare);
    distanceInput.addEventListener('input', calculateRideFare);
    // Initial run
    calculateRideFare();
  }

  // 4. Live Vehicle Rental Duration & Price Calculation
  const rentalVehicleSelect = document.getElementById('rental_vehicle');
  const startDateInput = document.getElementById('start_date');
  const endDateInput = document.getElementById('end_date');
  const dailyRateDisplay = document.getElementById('daily_rate_display');
  const durationDisplay = document.getElementById('rental_days_display');
  const rentalTotalDisplay = document.getElementById('rental_total_display');
  const rentalTotalInput = document.getElementById('rental_total_input');
  const rentalDaysInput = document.getElementById('rental_days_input');

  function calculateRentalTotal() {
    if (!rentalVehicleSelect || !startDateInput || !endDateInput) return;

    const selectedOpt = rentalVehicleSelect.options[rentalVehicleSelect.selectedIndex];
    if (!selectedOpt || !selectedOpt.value) return;

    const dailyRate = parseFloat(selectedOpt.getAttribute('data-daily') || 500.00);
    if (dailyRateDisplay) dailyRateDisplay.textContent = '$' + dailyRate.toFixed(2);

    const startVal = startDateInput.value;
    const endVal = endDateInput.value;

    if (startVal && endVal) {
      const start = new Date(startVal);
      const end = new Date(endVal);

      if (end >= start) {
        // Calculate days difference (inclusive of start and end)
        const diffTime = Math.abs(end - start);
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;

        const totalAmount = diffDays * dailyRate;

        if (durationDisplay) durationDisplay.textContent = diffDays + ' Day' + (diffDays > 1 ? 's' : '');
        if (rentalTotalDisplay) rentalTotalDisplay.textContent = '$' + totalAmount.toFixed(2);
        if (rentalTotalInput) rentalTotalInput.value = totalAmount.toFixed(2);
        if (rentalDaysInput) rentalDaysInput.value = diffDays;
      } else {
        if (durationDisplay) durationDisplay.textContent = 'Invalid Dates';
        if (rentalTotalDisplay) rentalTotalDisplay.textContent = '$0.00';
      }
    }
  }

  if (rentalVehicleSelect && startDateInput && endDateInput) {
    rentalVehicleSelect.addEventListener('change', calculateRentalTotal);
    startDateInput.addEventListener('change', calculateRentalTotal);
    endDateInput.addEventListener('change', calculateRentalTotal);
    calculateRentalTotal();
  }

  // 5. Live Courier Fee Calculation
  const parcelWeightInput = document.getElementById('parcel_weight');
  const deliveryOptionRadios = document.querySelectorAll('input[name="delivery_option"]');
  const courierFeeDisplay = document.getElementById('courier_fee_display');
  const courierFeeInput = document.getElementById('courier_fee_input');

  function calculateCourierFee() {
    if (!parcelWeightInput || !courierFeeDisplay) return;

    const weight = parseFloat(parcelWeightInput.value) || 1.0;
    let basePrice = 40.00;
    let perKgRate = 15.00;

    let selectedOption = 'Standard Delivery';
    deliveryOptionRadios.forEach(radio => {
      if (radio.checked) {
        selectedOption = radio.value;
      }
    });

    if (selectedOption === 'Express Delivery') {
      basePrice = 70.00;
      perKgRate = 25.00;
    } else if (selectedOption === 'Same Day Delivery') {
      basePrice = 100.00;
      perKgRate = 35.00;
    }

    const fee = basePrice + (Math.max(0, weight - 1) * perKgRate);

    courierFeeDisplay.textContent = '$' + fee.toFixed(2);
    if (courierFeeInput) courierFeeInput.value = fee.toFixed(2);
  }

  if (parcelWeightInput) {
    parcelWeightInput.addEventListener('input', calculateCourierFee);
    deliveryOptionRadios.forEach(radio => {
      radio.addEventListener('change', calculateCourierFee);
    });
    calculateCourierFee();
  }

  // 6. Handle URL Hash on Dashboard to switch tabs automatically
  if (window.location.hash) {
    const hash = window.location.hash.substring(1);
    const targetTabBtn = document.querySelector(`.dash-tab-btn[data-tab="${hash}"]`);
    if (targetTabBtn) {
      targetTabBtn.click();
    }
  }
});
