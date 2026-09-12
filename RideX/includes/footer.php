<?php
// includes/footer.php
?>
<!-- RideX Global Footer -->
<footer class="footer">
    <div class="container">
        <div class="footer-grid">
            <!-- Brand Column -->
            <div class="footer-brand">
                <a href="index.php" class="brand-logo" title="RideX – Smart Multi-Vehicle Rental, Booking & Courier Delivery System">
                    <div class="brand-icon">
                        <i class="fa-solid fa-bolt-lightning"></i>
                    </div>
                    Ride<span style="color:#38bdf8;">X</span>
                </a>
                <p><strong>RideX – Smart Multi-Vehicle Rental, Booking & Courier Delivery System</strong>. All-in-one transportation and logistics ecosystem built for reliability, speed, and safety.</p>
                <div style="display: flex; gap: 12px; margin-top: 15px; font-size: 18px;">
                    <a href="#" style="color:#94a3b8;"><i class="fa-brands fa-facebook"></i></a>
                    <a href="#" style="color:#94a3b8;"><i class="fa-brands fa-twitter"></i></a>
                    <a href="#" style="color:#94a3b8;"><i class="fa-brands fa-instagram"></i></a>
                    <a href="#" style="color:#94a3b8;"><i class="fa-brands fa-linkedin"></i></a>
                </div>
            </div>

            <!-- Quick Links -->
            <div class="footer-col">
                <h4>Quick Navigation</h4>
                <ul class="footer-links">
                    <li><a href="index.php">Home Page</a></li>
                    <li><a href="vehicles.php">All Fleet Vehicles</a></li>
                    <li><a href="booking.php">Book Instant Ride</a></li>
                    <li><a href="rental.php">Self-Drive Rentals</a></li>
                    <li><a href="courier.php">Send Courier Parcel</a></li>
                    <li><a href="tracking.php">Track Delivery</a></li>
                </ul>
            </div>

            <!-- Vehicle Fleet -->
            <div class="footer-col">
                <h4>Vehicle Categories</h4>
                <ul class="footer-links">
                    <li><a href="vehicles.php?category=Bike">Bikes & Cruisers</a></li>
                    <li><a href="vehicles.php?category=Scooter">Scooters & Mopeds</a></li>
                    <li><a href="vehicles.php?category=Auto+Rickshaw">Auto Rickshaws</a></li>
                    <li><a href="vehicles.php?category=Car">Sedans & Compacts</a></li>
                    <li><a href="vehicles.php?category=SUV">SUVs & Off-Road</a></li>
                    <li><a href="vehicles.php?category=Commercial+Vehicle">Commercial & Haulers</a></li>
                </ul>
            </div>

            <!-- Contact & Help -->
            <div class="footer-col">
                <h4>Support & Inquiries</h4>
                <ul class="footer-links">
                    <li><i class="fa-solid fa-phone" style="margin-right:8px; color:#38bdf8;"></i> +1 (800) 555-RIDEX</li>
                    <li><i class="fa-solid fa-envelope" style="margin-right:8px; color:#38bdf8;"></i> support@ridex.com</li>
                    <li><i class="fa-solid fa-location-dot" style="margin-right:8px; color:#38bdf8;"></i> 100 Innovation Way, Tech District</li>
                </ul>
            </div>
        </div>

        <!-- Bottom Copyright -->
        <div class="footer-bottom">
            <p>&copy; <?= date('Y') ?> RideX Inc. All Rights Reserved. BSc Computer Science Final Year Project.</p>
            <p>Designed with PHP 8, MySQL, Apache & Modern JavaScript.</p>
        </div>
    </div>
</footer>

<!-- Scripts -->
<script src="js/script.js"></script>
</body>
</html>
