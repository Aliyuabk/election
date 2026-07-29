<?php
// ============================================================
// CITIZEN PORTAL - ELECTION INFORMATION
// ============================================================
require_once '../../config/config.php';
require_once '../../includes/functions.php';

$page_title = 'Election Information';
$current_page = 'info';

$db = getDB();

// Get elections for calendar
$elections = [];
try {
    $stmt = $db->prepare("
        SELECT id, name, type, election_date, status 
        FROM elections 
        ORDER BY election_date DESC 
        LIMIT 10
    ");
    $stmt->execute();
    $elections = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Error fetching elections: " . $e->getMessage());
}

include '../includes/public-header.php';
?>

<style>
.info-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 24px;
}
.info-section {
    background: white;
    border-radius: 14px;
    padding: 24px;
    border: 1px solid #E5E7EB;
    margin-bottom: 20px;
}
.info-section .section-title {
    font-size: 1.1rem;
    font-weight: 700;
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 10px;
}
.info-section .section-title i {
    color: #0F4C81;
}

.faq-item {
    padding: 14px 0;
    border-bottom: 1px solid #F3F4F6;
}
.faq-item:last-child {
    border-bottom: none;
}
.faq-item .question {
    font-weight: 600;
    font-size: 0.95rem;
    cursor: pointer;
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 4px 0;
}
.faq-item .question:hover {
    color: #0F4C81;
}
.faq-item .question .toggle-icon {
    transition: transform 0.3s;
}
.faq-item .question .toggle-icon.open {
    transform: rotate(180deg);
}
.faq-item .answer {
    display: none;
    padding-top: 8px;
    color: #4B5563;
    font-size: 0.9rem;
    line-height: 1.6;
}
.faq-item .answer.open {
    display: block;
}

.calendar-item {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 12px 0;
    border-bottom: 1px solid #F3F4F6;
}
.calendar-item:last-child {
    border-bottom: none;
}
.calendar-item .date-box {
    background: #0F4C81;
    color: white;
    border-radius: 10px;
    padding: 6px 12px;
    text-align: center;
    min-width: 60px;
}
.calendar-item .date-box .day {
    font-size: 1.2rem;
    font-weight: 700;
    display: block;
}
.calendar-item .date-box .month {
    font-size: 0.6rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.calendar-item .event-info .event-name {
    font-weight: 600;
    font-size: 0.9rem;
}
.calendar-item .event-info .event-type {
    font-size: 0.75rem;
    color: #6B7280;
}
.calendar-item .event-info .event-status {
    font-size: 0.65rem;
    padding: 1px 10px;
    border-radius: 12px;
    display: inline-block;
    margin-top: 2px;
}
.calendar-item .event-info .event-status.active { background: #D1FAE5; color: #059669; }
.calendar-item .event-info .event-status.closed { background: #F3F4F6; color: #6B7280; }
.calendar-item .event-info .event-status.upcoming { background: #FEF3C7; color: #D97706; }

.guidelines-list {
    list-style: none;
    padding: 0;
}
.guidelines-list li {
    padding: 10px 0;
    border-bottom: 1px solid #F3F4F6;
    display: flex;
    gap: 12px;
    align-items: flex-start;
}
.guidelines-list li:last-child {
    border-bottom: none;
}
.guidelines-list li .num {
    background: #0F4C81;
    color: white;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    font-weight: 700;
    flex-shrink: 0;
}
.guidelines-list li .content {
    font-size: 0.9rem;
}
.guidelines-list li .content strong {
    display: block;
    font-weight: 600;
}

@media (max-width: 768px) {
    .info-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="container">
    <h1 style="font-size:1.4rem;font-weight:700;margin-bottom:6px;">
        <i class="fas fa-info-circle" style="color:#0F4C81;"></i> Election Information
    </h1>
    <p style="color:#6B7280;margin-bottom:24px;font-size:0.9rem;">
        Important information about elections, guidelines, and frequently asked questions.
    </p>

    <div class="info-grid">
        <div>
            <!-- FAQ Section -->
            <div class="info-section" id="faq">
                <div class="section-title">
                    <i class="fas fa-question-circle"></i> Frequently Asked Questions
                </div>

                <div class="faq-item">
                    <div class="question" onclick="toggleFAQ(this)">
                        How do I find my polling unit?
                        <span class="toggle-icon"><i class="fas fa-chevron-down"></i></span>
                    </div>
                    <div class="answer">
                        You can find your polling unit by using the <strong>Search Polling Units</strong> feature on this portal. 
                        Simply enter your location, LGA, or polling unit name to find your designated polling unit.
                        You can also visit your local INEC office for assistance.
                    </div>
                </div>

                <div class="faq-item">
                    <div class="question" onclick="toggleFAQ(this)">
                        When will the election results be published?
                        <span class="toggle-icon"><i class="fas fa-chevron-down"></i></span>
                    </div>
                    <div class="answer">
                        Election results are published as soon as they are officially verified and cleared by the 
                        electoral body. Results are typically published within 24-48 hours after the election 
                        day. You can check the <strong>Published Results</strong> section for the latest updates.
                    </div>
                </div>

                <div class="faq-item">
                    <div class="question" onclick="toggleFAQ(this)">
                        How can I verify the authenticity of published results?
                        <span class="toggle-icon"><i class="fas fa-chevron-down"></i></span>
                    </div>
                    <div class="answer">
                        All results published on this portal are officially verified and cleared by the electoral body.
                        Each result includes a publication date and the name of the verifying officer. 
                        You can also cross-reference results with the official election website.
                    </div>
                </div>

                <div class="faq-item">
                    <div class="question" onclick="toggleFAQ(this)">
                        What information is included in the candidate profiles?
                        <span class="toggle-icon"><i class="fas fa-chevron-down"></i></span>
                    </div>
                    <div class="answer">
                        Candidate profiles include the candidate's full name, photograph, political party affiliation, 
                        position contested, contact information, biography, and manifesto. This information is 
                        provided to help voters make informed decisions.
                    </div>
                </div>

                <div class="faq-item">
                    <div class="question" onclick="toggleFAQ(this)">
                        How often is the information on this portal updated?
                        <span class="toggle-icon"><i class="fas fa-chevron-down"></i></span>
                    </div>
                    <div class="answer">
                        Information on this portal is updated in real-time as new results are published and verified. 
                        Candidate profiles and election information are updated as soon as new data is available.
                    </div>
                </div>

                <div class="faq-item">
                    <div class="question" onclick="toggleFAQ(this)">
                        Can I report an issue or concern?
                        <span class="toggle-icon"><i class="fas fa-chevron-down"></i></span>
                    </div>
                    <div class="answer">
                        Yes, you can report any issues or concerns by visiting the <strong>Contact</strong> page. 
                        You can also reach out to our support team via email or phone for immediate assistance.
                    </div>
                </div>
            </div>

            <!-- Election Guidelines -->
            <div class="info-section">
                <div class="section-title">
                    <i class="fas fa-gavel"></i> Election Guidelines
                </div>
                <ul class="guidelines-list">
                    <li>
                        <span class="num">1</span>
                        <div class="content">
                            <strong>Voter Registration</strong>
                            Ensure you are registered to vote. Voter registration closes at least 60 days before the election.
                        </div>
                    </li>
                    <li>
                        <span class="num">2</span>
                        <div class="content">
                            <strong>Voter Identification</strong>
                            Bring your valid voter ID card to the polling unit. No other form of identification is accepted.
                        </div>
                    </li>
                    <li>
                        <span class="num">3</span>
                        <div class="content">
                            <strong>Voting Process</strong>
                            Follow the instructions of the polling officials. Cast your vote in secret and leave the polling unit quietly.
                        </div>
                    </li>
                    <li>
                        <span class="num">4</span>
                        <div class="content">
                            <strong>Election Conduct</strong>
                            No campaigning, intimidation, or violence is allowed within the polling unit premises.
                        </div>
                    </li>
                    <li>
                        <span class="num">5</span>
                        <div class="content">
                            <strong>Result Transparency</strong>
                            Results are announced at the polling unit level and progressively aggregated at the ward, LGA, and state levels.
                        </div>
                    </li>
                    <li>
                        <span class="num">6</span>
                        <div class="content">
                            <strong>Complaints</strong>
                            Any complaints about the election process should be reported to the electoral body immediately.
                        </div>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Sidebar -->
        <div>
            <!-- Election Calendar -->
            <div class="info-section" id="calendar">
                <div class="section-title">
                    <i class="fas fa-calendar-alt"></i> Election Calendar
                </div>
                <?php if (!empty($elections)): ?>
                    <?php foreach ($elections as $election): ?>
                        <div class="calendar-item">
                            <div class="date-box">
                                <span class="day"><?php echo date('d', strtotime($election['election_date'])); ?></span>
                                <span class="month"><?php echo date('M', strtotime($election['election_date'])); ?></span>
                            </div>
                            <div class="event-info">
                                <div class="event-name"><?php echo htmlspecialchars($election['name']); ?></div>
                                <div class="event-type"><?php echo ucfirst(str_replace('_', ' ', $election['type'])); ?></div>
                                <span class="event-status <?php echo $election['status']; ?>">
                                    <?php echo ucfirst($election['status']); ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="color:#6B7280;font-size:0.9rem;">No upcoming elections scheduled.</p>
                <?php endif; ?>
            </div>

            <!-- Quick Links -->
            <div class="info-section">
                <div class="section-title">
                    <i class="fas fa-bolt"></i> Quick Links
                </div>
                <div style="display:flex;flex-direction:column;gap:8px;">
                    <a href="published-results.php" style="padding:10px 14px;background:#F8FAFC;border-radius:10px;color:#0F4C81;text-decoration:none;font-weight:500;transition:background 0.2s;">
                        <i class="fas fa-file-alt"></i> View Published Results
                    </a>
                    <a href="search-polling-units.php" style="padding:10px 14px;background:#F8FAFC;border-radius:10px;color:#0F4C81;text-decoration:none;font-weight:500;transition:background 0.2s;">
                        <i class="fas fa-search"></i> Find Polling Unit
                    </a>
                    <a href="candidates.php" style="padding:10px 14px;background:#F8FAFC;border-radius:10px;color:#0F4C81;text-decoration:none;font-weight:500;transition:background 0.2s;">
                        <i class="fas fa-user-tie"></i> View Candidates
                    </a>
                    <a href="contact.php" style="padding:10px 14px;background:#F8FAFC;border-radius:10px;color:#0F4C81;text-decoration:none;font-weight:500;transition:background 0.2s;">
                        <i class="fas fa-envelope"></i> Contact Us
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function toggleFAQ(element) {
    var answer = element.nextElementSibling;
    var icon = element.querySelector('.toggle-icon');
    
    if (answer.classList.contains('open')) {
        answer.classList.remove('open');
        if (icon) {
            icon.classList.remove('open');
            icon.innerHTML = '<i class="fas fa-chevron-down"></i>';
        }
    } else {
        answer.classList.add('open');
        if (icon) {
            icon.classList.add('open');
            icon.innerHTML = '<i class="fas fa-chevron-up"></i>';
        }
    }
}

// Open first FAQ by default
document.addEventListener('DOMContentLoaded', function() {
    var firstFaq = document.querySelector('.faq-item .question');
    if (firstFaq) {
        toggleFAQ(firstFaq);
    }
});
</script>

<?php include '../includes/public-footer.php'; ?>