<?php
session_start();

// Database Connection
$host = 'localhost';
$dbname = 'data_capture';
$username = 'root';
$password = '';

$conn = mysqli_connect($host, $username, $password, $dbname);
if (!$conn) {
    if (isset($_POST['action']) || isset($_GET['action'])) {
        header('Content-Type: application/json');
        echo json_encode(['error' => "Connection failed: " . mysqli_connect_error()]);
        exit;
    } else {
        die("Connection failed: " . mysqli_connect_error());
    }
}

// Process Actions (AJAX Handlers)
if (isset($_POST['action']) || isset($_GET['action'])) {
    header('Content-Type: application/json');

    function generateClientCode($conn, $clientName) {
        // Remove non-alphabetic characters and split into words
        $cleanName = preg_replace('/[^A-Za-z\s]/', '', $clientName);
        $words = array_filter(explode(' ', trim($cleanName)));
        
        // Initialize the alphabetic part
        $alpha = '';
        
        if (count($words) >= 3) {
            // For names with 3 or more words, take first letter of each of first 3 words
            $alpha = strtoupper(
                substr($words[0], 0, 1) . 
                (isset($words[1]) ? substr($words[1], 0, 1) : 'A') . 
                (isset($words[2]) ? substr($words[2], 0, 1) : 'A')
            );
        } elseif (count($words) == 2) {
            // For names with 2 words, take first letter of first word, first letter of second word, and second letter of second word
            $alpha = strtoupper(
                substr($words[0], 0, 1) . 
                substr($words[1], 0, 1) . 
                (strlen($words[1]) > 1 ? substr($words[1], 1, 1) : 'A')
            );
        } else {
            // For single word or no valid words, take first 3 letters
            $word = $words[0] ?? '';
            $alpha = strtoupper(substr($word, 0, 3));
            if (empty($alpha)) {
                $alpha = 'AAA'; // Fallback for no valid input
            }
        }
        
        // Generate unique code with 3-digit number
        $num = 1;
        do {
            $code = $alpha . sprintf("%03d", $num);
            $query = "SELECT COUNT(*) as count FROM clients WHERE client_code = ?";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, "s", $code);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $row = mysqli_fetch_assoc($result);
            $exists = $row['count'] > 0;
            mysqli_stmt_close($stmt);
            $num++;
        } while ($exists);
        
        return $code;
    }

    $action = $_POST['action'] ?? $_GET['action'] ?? '';

    if ($action == 'create_client') {
        $name = trim($_POST['name'] ?? '');
        if (empty($name)) {
            echo json_encode(['error' => 'Client name is required.']);
            exit;
        }
        $client_code = generateClientCode($conn, $name);
        $stmt = mysqli_prepare($conn, "INSERT INTO clients (name, client_code) VALUES (?, ?)");
        mysqli_stmt_bind_param($stmt, "ss", $name, $client_code);
        if (mysqli_stmt_execute($stmt)) {
            $client_id = mysqli_insert_id($conn);
            echo json_encode(['success' => 'Client created successfully.', 'client_id' => $client_id, 'client_code' => $client_code]);
        } else {
            echo json_encode(['error' => 'Error creating client: ' . mysqli_error($conn)]);
        }
        mysqli_stmt_close($stmt);
        exit;
    }

    if ($action == 'create_contact') {
        $name = trim($_POST['name'] ?? '');
        $surname = trim($_POST['surname'] ?? '');
        $email = trim($_POST['email'] ?? '');
        if (empty($name) || empty($surname) || empty($email)) {
            echo json_encode(['error' => 'All fields are required.']);
            exit;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['error' => 'Invalid email format.']);
            exit;
        }
        $stmt = mysqli_prepare($conn, "INSERT INTO contacts (name, surname, email) VALUES (?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "sss", $name, $surname, $email);
        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(['success' => 'Contact created successfully.', 'contact_id' => mysqli_insert_id($conn)]);
        } else {
            echo json_encode(['error' => 'Email already exists or error: ' . mysqli_error($conn)]);
        }
        mysqli_stmt_close($stmt);
        exit;
    }

    if ($action == 'link_contact') {
        $client_id = (int)($_POST['client_id'] ?? 0);
        $contact_id = (int)($_POST['contact_id'] ?? 0);
        if ($client_id <= 0 || $contact_id <= 0) {
            echo json_encode(['error' => 'Invalid client or contact ID.']);
            exit;
        }
        $stmt = mysqli_prepare($conn, "INSERT INTO client_contacts (client_id, contact_id) VALUES (?, ?)");
        mysqli_stmt_bind_param($stmt, "ii", $client_id, $contact_id);
        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(['success' => 'Contact linked successfully.']);
        } else {
            echo json_encode(['error' => 'Contact already linked or error: ' . mysqli_error($conn)]);
        }
        mysqli_stmt_close($stmt);
        exit;
    }

    if ($action == 'unlink_contact') {
        $client_id = (int)($_GET['client_id'] ?? 0);
        $contact_id = (int)($_GET['contact_id'] ?? 0);
        if ($client_id <= 0 || $contact_id <= 0) {
            echo json_encode(['error' => 'Invalid client or contact ID.']);
            exit;
        }
        $stmt = mysqli_prepare($conn, "DELETE FROM client_contacts WHERE client_id = ? AND contact_id = ?");
        mysqli_stmt_bind_param($stmt, "ii", $client_id, $contact_id);
        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(['success' => 'Contact unlinked successfully.']);
        } else {
            echo json_encode(['error' => 'Error unlinking contact: ' . mysqli_error($conn)]);
        }
        mysqli_stmt_close($stmt);
        exit;
    }

    if ($action == 'link_client') {
        $contact_id = (int)($_POST['contact_id'] ?? 0);
        $client_id = (int)($_POST['client_id'] ?? 0);
        if ($contact_id <= 0 || $client_id <= 0) {
            echo json_encode(['error' => 'Invalid client or contact ID.']);
            exit;
        }
        $stmt = mysqli_prepare($conn, "INSERT INTO client_contacts (client_id, contact_id) VALUES (?, ?)");
        mysqli_stmt_bind_param($stmt, "ii", $client_id, $contact_id);
        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(['success' => 'Client linked successfully.']);
        } else {
            echo json_encode(['error' => 'Client already linked or error: ' . mysqli_error($conn)]);
        }
        mysqli_stmt_close($stmt);
        exit;
    }

    if ($action == 'unlink_client') {
        $contact_id = (int)($_GET['contact_id'] ?? 0);
        $client_id = (int)($_GET['client_id'] ?? 0);
        if ($contact_id <= 0 || $client_id <= 0) {
            echo json_encode(['error' => 'Invalid client or contact ID.']);
            exit;
        }
        $stmt = mysqli_prepare($conn, "DELETE FROM client_contacts WHERE client_id = ? AND contact_id = ?");
        mysqli_stmt_bind_param($stmt, "ii", $client_id, $contact_id);
        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(['success' => 'Client unlinked successfully.']);
        } else {
            echo json_encode(['error' => 'Error unlinking client: ' . mysqli_error($conn)]);
        }
        mysqli_stmt_close($stmt);
        exit;
    }

    if ($action == 'get_linked_contacts') {
        $client_id = (int)($_GET['client_id'] ?? 0);
        if ($client_id <= 0) {
            echo json_encode(['error' => 'Invalid client ID.']);
            exit;
        }
        $stmt = mysqli_prepare($conn, "SELECT c.id, c.name, c.surname, c.email 
                                      FROM contacts c 
                                      INNER JOIN client_contacts cc ON c.id = cc.contact_id 
                                      WHERE cc.client_id = ? 
                                      ORDER BY c.surname, c.name");
        mysqli_stmt_bind_param($stmt, "i", $client_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $contacts = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $contacts[] = $row;
        }
        mysqli_stmt_close($stmt);

        $stmt = mysqli_prepare($conn, "SELECT id, name, surname, email 
                                      FROM contacts 
                                      WHERE id NOT IN (SELECT contact_id FROM client_contacts WHERE client_id = ?) 
                                      ORDER BY surname, name");
        mysqli_stmt_bind_param($stmt, "i", $client_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $available_contacts = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $available_contacts[] = $row;
        }
        mysqli_stmt_close($stmt);

        echo json_encode(['linked_contacts' => $contacts, 'available_contacts' => $available_contacts]);
        exit;
    }

    if ($action == 'get_linked_clients') {
        $contact_id = (int)($_GET['contact_id'] ?? 0);
        if ($contact_id <= 0) {
            echo json_encode(['error' => 'Invalid contact ID.']);
            exit;
        }
        $stmt = mysqli_prepare($conn, "SELECT c.id, c.name, c.client_code 
                                      FROM clients c 
                                      INNER JOIN client_contacts cc ON c.id = cc.client_id 
                                      WHERE cc.contact_id = ? 
                                      ORDER BY c.name");
        mysqli_stmt_bind_param($stmt, "i", $contact_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $clients = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $clients[] = $row;
        }
        mysqli_stmt_close($stmt);

        $stmt = mysqli_prepare($conn, "SELECT id, name, client_code 
                                      FROM clients 
                                      WHERE id NOT IN (SELECT client_id FROM client_contacts WHERE contact_id = ?) 
                                      ORDER BY name");
        mysqli_stmt_bind_param($stmt, "i", $contact_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $available_clients = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $available_clients[] = $row;
        }
        mysqli_stmt_close($stmt);

        echo json_encode(['linked_clients' => $clients, 'available_clients' => $available_clients]);
        exit;
    }
}

// Routing Logic and Content Preparation
$page = $_GET['page'] ?? 'clients';
$content = '';
$client_id = 0;
$client_name = '';
$client_code = '';
$contact_id = 0;
$contact_name = '';
$contact_surname = '';
$contact_email = '';
$has_client = false;
$has_contact = false;

if ($page == 'clients') {
    $result = mysqli_query($conn, "SELECT c.id, c.name, c.client_code, COUNT(cc.contact_id) as contact_count 
                                  FROM clients c 
                                  LEFT JOIN client_contacts cc ON c.id = cc.client_id 
                                  GROUP BY c.id 
                                  ORDER BY c.name ASC");
    $clients = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $clients[] = $row;
    }
    ob_start();
    ?>
    <h1>Clients</h1>
    <div id="message"></div>
    <a href="?page=client_form">Create New Client</a>
    <?php if (empty($clients)): ?>
        <p>No client(s) found.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Client Code</th>
                    <th>No. of Linked Contacts</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($clients as $client): ?>
                    <tr>
                        <td><a href="?page=client_form&id=<?php echo $client['id']; ?>"><?php echo htmlspecialchars($client['name']); ?></a></td>
                        <td><?php echo htmlspecialchars($client['client_code']); ?></td>
                        <td class="center"><?php echo $client['contact_count']; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    <a href="?page=contacts">View Contacts</a>
    <?php
    $content = ob_get_clean();
}

if ($page == 'client_form') {
    if (isset($_GET['id'])) {
        $id = (int)$_GET['id'];
        $stmt = mysqli_prepare($conn, "SELECT * FROM clients WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if ($row = mysqli_fetch_assoc($result)) {
            $client_id = $row['id'];
            $client_name = $row['name'];
            $client_code = $row['client_code'];
            $has_client = true;
        }
        mysqli_stmt_close($stmt);
    }
    ob_start();
    ?>
    <h1><?php echo $has_client ? 'Edit Client' : 'Create Client'; ?></h1>
    <div id="message"></div>
    <div class="tabs">
        <button class="tablink" onclick="openTab(event, 'general')">General</button>
        <?php if ($has_client): ?>
            <button class="tablink" onclick="openTab(event, 'contacts'); loadLinkedContacts(<?php echo $client_id; ?>)">Contact(s)</button>
        <?php endif; ?>
    </div>
    <form id="client_form" class="tabcontent" style="display: block;">
        <input type="hidden" name="action" value="create_client">
        <label for="name">Name:</label>
        <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($client_name); ?>" required>
        <span id="name_error" class="error"></span>
        <?php if ($has_client): ?>
            <label for="client_code">Client Code:</label>
            <input type="text" id="client_code" name="client_code" value="<?php echo htmlspecialchars($client_code); ?>" readonly>
        <?php endif; ?>
        <button type="submit">Save</button>
    </form>
    <?php if ($has_client): ?>
        <div class="tabcontent" id="contacts">
            <div id="contacts_list"></div>
            <h3>Link New Contact</h3>
            <form id="link_contact_form">
                <input type="hidden" name="action" value="link_contact">
                <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
                <select name="contact_id" id="contact_id" required>
                    <option value="">Select Contact</option>
                </select>
                <span id="contact_id_error" class="error"></span>
                <button type="submit">Link Contact</button>
            </form>
        </div>
    <?php endif; ?>
    <a href="?page=clients">Back to Clients</a>
    <?php
    $content = ob_get_clean();
}

if ($page == 'contacts') {
    $result = mysqli_query($conn, "SELECT c.id, c.name, c.surname, c.email, COUNT(cc.client_id) as client_count 
                                  FROM contacts c 
                                  LEFT JOIN client_contacts cc ON c.id = cc.contact_id 
                                  GROUP BY c.id 
                                  ORDER BY c.surname, c.name");
    $contacts = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $contacts[] = $row;
    }
    ob_start();
    ?>
    <h1>Contacts</h1>
    <div id="message"></div>
    <a href="?page=contact_form">Create New Contact</a>
    <?php if (empty($contacts)): ?>
        <p>No contact(s) found.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Surname</th>
                    <th>Email Address</th>
                    <th>No. of Linked Clients</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($contacts as $contact): ?>
                    <tr>
                        <td><a href="?page=contact_form&id=<?php echo $contact['id']; ?>"><?php echo htmlspecialchars($contact['name']); ?></a></td>
                        <td><?php echo htmlspecialchars($contact['surname']); ?></td>
                        <td><?php echo htmlspecialchars($contact['email']); ?></td>
                        <td class="center"><?php echo $contact['client_count']; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    <a href="?page=clients">View Clients</a>
    <?php
    $content = ob_get_clean();
}

if ($page == 'contact_form') {
    if (isset($_GET['id'])) {
        $id = (int)$_GET['id'];
        $stmt = mysqli_prepare($conn, "SELECT * FROM contacts WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if ($row = mysqli_fetch_assoc($result)) {
            $contact_id = $row['id'];
            $contact_name = $row['name'];
            $contact_surname = $row['surname'];
            $contact_email = $row['email'];
            $has_contact = true;
        }
        mysqli_stmt_close($stmt);
    }
    ob_start();
    ?>
    <h1><?php echo $has_contact ? 'Edit Contact' : 'Create Contact'; ?></h1>
    <div id="message"></div>
    <div class="tabs">
        <button class="tablink" onclick="openTab(event, 'general')">General</button>
        <?php if ($has_contact): ?>
            <button class="tablink" onclick="openTab(event, 'clients'); loadLinkedClients(<?php echo $contact_id; ?>)">Client(s)</button>
        <?php endif; ?>
    </div>
    <form id="contact_form" class="tabcontent" style="display: block;">
        <input type="hidden" name="action" value="create_contact">
        <label for="name">Name:</label>
        <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($contact_name); ?>" required>
        <span id="name_error" class="error"></span>
        <label for="surname">Surname:</label>
        <input type="text" id="surname" name="surname" value="<?php echo htmlspecialchars($contact_surname); ?>" required>
        <span id="surname_error" class="error"></span>
        <label for="email">Email:</label>
        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($contact_email); ?>" required>
        <span id="email_error" class="error"></span>
        <button type="submit">Save</button>
    </form>
    <?php if ($has_contact): ?>
        <div class="tabcontent" id="clients">
            <div id="clients_list"></div>
            <h3>Link New Client</h3>
            <form id="link_client_form">
                <input type="hidden" name="action" value="link_client">
                <input type="hidden" name="contact_id" value="<?php echo $contact_id; ?>">
                <select name="client_id" id="client_id" required>
                    <option value="">Select Client</option>
                </select>
                <span id="client_id_error" class="error"></span>
                <button type="submit">Link Client</button>
            </form>
        </div>
    <?php endif; ?>
    <a href="?page=contacts">Back to Contacts</a>
    <?php
    $content = ob_get_clean();
}

mysqli_close($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Data Capture App</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
        }
        h1 {
            text-align: center;
        }
        table {
            width: 80%;
            margin: 20px 0;
            border-collapse: collapse;
        }
        th, td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: left;
        }
        th.center, td.center {
            text-align: center;
        }
        th {
            background-color: #f2f2f2;
        }
        a {
            color: #0066cc;
            text-decoration: none;
            margin: 10px 0;
            display: inline-block;
        }
        a:hover {
            text-decoration: underline;
        }
        .error {
            color: red;
            font-size: 0.9em;
            margin-top: 5px;
            display: block;
        }
        .success {
            color: green;
            padding: 10px;
            background-color: #e6ffe6;
        }
        #message {
            margin-bottom: 10px;
        }
        form {
            display: flex;
            flex-direction: column;
            width: 300px;
        }
        label {
            margin-top: 10px;
        }
        input, select, button {
            padding: 5px;
            margin-top: 5px;
        }
        button {
            background-color: #0066cc;
            color: white;
            border: none;
            padding: 10px;
            cursor: pointer;
            width: 100%;
        }
        button:hover {
            background-color: #0055aa;
        }
        button:disabled {
            background-color: #cccccc;
            cursor: not-allowed;
        }
        .tabs {
            overflow: hidden;
            border-bottom: 1px solid #ccc;
            margin-bottom: 10px;
        }
        .tablink {
            background-color: #f2f2f2;
            float: left;
            border: none;
            outline: none;
            cursor: pointer;
            padding: 14px 16px;
            transition: 0.3s;
        }
        .tablink:hover {
            background-color: #ddd;
        }
        .tablink.active {
            background-color: #fff;
            border-bottom: 2px solid #0066cc;
        }
        .tabcontent {
            padding: 20px;
            border: 1px solid #ccc;
            border-top: none;
            display: none;
        }
        .tabcontent.active {
            display: block;
        }
    </style>
</head>
<body>
    <?php echo $content; ?>
    <script>
        function openTab(evt, tabName) {
            const tabcontent = document.getElementsByClassName("tabcontent");
            for (let i = 0; i < tabcontent.length; i++) {
                tabcontent[i].classList.remove("active");
            }
            const tablinks = document.getElementsByClassName("tablink");
            for (let i = 0; i < tablinks.length; i++) {
                tablinks[i].className = tablinks[i].className.replace(" active", "");
            }
            const tabElement = document.getElementById(tabName);
            if (tabElement) {
                tabElement.classList.add("active");
            }
            evt.currentTarget.className += " active";
        }

        document.addEventListener('DOMContentLoaded', function() {
            const defaultTab = document.getElementsByClassName('tablink')[0];
            if (defaultTab) {
                defaultTab.click();
            }

            // Client Form Submission
            const clientForm = document.getElementById('client_form');
            if (clientForm) {
                clientForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    const name = document.getElementById('name').value.trim();
                    const nameError = document.getElementById('name_error');
                    const submitButton = clientForm.querySelector('button[type="submit"]');
                    let valid = true;

                    nameError.textContent = '';
                    if (!name) {
                        nameError.textContent = 'Client name is required.';
                        valid = false;
                    }

                    if (valid) {
                        submitButton.disabled = true;
                        const formData = new FormData(clientForm);
                        fetch('?action=create_client', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => {
                            if (!response.ok) {
                                throw new Error(`HTTP error! Status: ${response.status}`);
                            }
                            return response.json();
                        })
                        .then(data => {
                            const messageDiv = document.getElementById('message');
                            submitButton.disabled = false;
                            if (data.success) {
                                messageDiv.innerHTML = `<p class="success">${data.success}</p>`;
                                clientForm.reset();
                                if (data.client_id) {
                                    setTimeout(() => {
                                        window.location.href = `?page=client_form&id=${data.client_id}`;
                                    }, 1000);
                                }
                            } else {
                                messageDiv.innerHTML = `<p class="error">${data.error || 'Unknown error occurred.'}</p>`;
                            }
                        })
                        .catch(error => {
                            const messageDiv = document.getElementById('message');
                            messageDiv.innerHTML = `<p class="error">Error: ${error.message}</p>`;
                            submitButton.disabled = false;
                        });
                    }
                });
            }

            // Contact Form Submission
            const contactForm = document.getElementById('contact_form');
            if (contactForm) {
                contactForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    const name = document.getElementById('name').value.trim();
                    const surname = document.getElementById('surname').value.trim();
                    const email = document.getElementById('email').value.trim();
                    const nameError = document.getElementById('name_error');
                    const surnameError = document.getElementById('surname_error');
                    const emailError = document.getElementById('email_error');
                    const submitButton = contactForm.querySelector('button[type="submit"]');
                    let valid = true;

                    nameError.textContent = '';
                    surnameError.textContent = '';
                    emailError.textContent = '';

                    if (!name) {
                        nameError.textContent = 'Name is required.';
                        valid = false;
                    }
                    if (!surname) {
                        surnameError.textContent = 'Surname is required.';
                        valid = false;
                    }
                    if (!email) {
                        emailError.textContent = 'Email is required.';
                        valid = false;
                    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                        emailError.textContent = 'Invalid email format.';
                        valid = false;
                    }

                    if (valid) {
                        submitButton.disabled = true;
                        const formData = new FormData(contactForm);
                        fetch('?action=create_contact', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => {
                            if (!response.ok) {
                                throw new Error(`HTTP error! Status: ${response.status}`);
                            }
                            return response.json();
                        })
                        .then(data => {
                            const messageDiv = document.getElementById('message');
                            submitButton.disabled = false;
                            if (data.success) {
                                messageDiv.innerHTML = `<p class="success">${data.success}</p>`;
                                contactForm.reset();
                                if (data.contact_id) {
                                    setTimeout(() => {
                                        window.location.href = `?page=contact_form&id=${data.contact_id}`;
                                    }, 1000);
                                }
                            } else {
                                messageDiv.innerHTML = `<p class="error">${data.error || 'Unknown error occurred.'}</p>`;
                            }
                        })
                        .catch(error => {
                            const messageDiv = document.getElementById('message');
                            messageDiv.innerHTML = `<p class="error">Error: ${error.message}</p>`;
                            submitButton.disabled = false;
                        });
                    }
                });
            }

            // Link Contact Form Submission
            const linkContactForm = document.getElementById('link_contact_form');
            if (linkContactForm) {
                linkContactForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    const contactId = document.getElementById('contact_id').value;
                    const contactIdError = document.getElementById('contact_id_error');
                    const submitButton = linkContactForm.querySelector('button[type="submit"]');
                    let valid = true;

                    contactIdError.textContent = '';
                    if (!contactId) {
                        contactIdError.textContent = 'Please select a contact.';
                        valid = false;
                    }

                    if (valid) {
                        submitButton.disabled = true;
                        const formData = new FormData(linkContactForm);
                        fetch('?action=link_contact', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => {
                            if (!response.ok) {
                                throw new Error(`HTTP error! Status: ${response.status}`);
                            }
                            return response.json();
                        })
                        .then(data => {
                            const messageDiv = document.getElementById('message');
                            submitButton.disabled = false;
                            if (data.success) {
                                messageDiv.innerHTML = `<p class="success">${data.success}</p>`;
                                loadLinkedContacts(formData.get('client_id'));
                                linkContactForm.reset();
                            } else {
                                messageDiv.innerHTML = `<p class="error">${data.error || 'Unknown error occurred.'}</p>`;
                            }
                        })
                        .catch(error => {
                            const messageDiv = document.getElementById('message');
                            messageDiv.innerHTML = `<p class="error">Error: ${error.message}</p>`;
                            submitButton.disabled = false;
                        });
                    }
                });
            }

            // Link Client Form Submission
            const linkClientForm = document.getElementById('link_client_form');
            if (linkClientForm) {
                linkClientForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    const clientId = document.getElementById('client_id').value;
                    const clientIdError = document.getElementById('client_id_error');
                    const submitButton = linkClientForm.querySelector('button[type="submit"]');
                    let valid = true;

                    clientIdError.textContent = '';
                    if (!clientId) {
                        clientIdError.textContent = 'Please select a client.';
                        valid = false;
                    }

                    if (valid) {
                        submitButton.disabled = true;
                        const formData = new FormData(linkClientForm);
                        fetch('?action=link_client', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => {
                            if (!response.ok) {
                                throw new Error(`HTTP error! Status: ${response.status}`);
                            }
                            return response.json();
                        })
                        .then(data => {
                            const messageDiv = document.getElementById('message');
                            submitButton.disabled = false;
                            if (data.success) {
                                messageDiv.innerHTML = `<p class="success">${data.success}</p>`;
                                loadLinkedClients(formData.get('contact_id'));
                                linkClientForm.reset();
                            } else {
                                messageDiv.innerHTML = `<p class="error">${data.error || 'Unknown error occurred.'}</p>`;
                            }
                        })
                        .catch(error => {
                            const messageDiv = document.getElementById('message');
                            messageDiv.innerHTML = `<p class="error">Error: ${error.message}</p>`;
                            submitButton.disabled = false;
                        });
                    }
                });
            }
        });

        function loadLinkedContacts(clientId) {
            const contactsList = document.getElementById('contacts_list');
            const contactSelect = document.getElementById('contact_id');
            if (!contactsList || !contactSelect) return;

            fetch(`?action=get_linked_contacts&client_id=${clientId}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! Status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    contactsList.innerHTML = '';
                    contactSelect.innerHTML = '<option value="">Select Contact</option>';

                    if (data.error) {
                        contactsList.innerHTML = `<p class="error">${data.error}</p>`;
                        return;
                    }

                    if (data.linked_contacts.length === 0) {
                        contactsList.innerHTML = '<p>No contact(s) found.</p>';
                    } else {
                        let table = '<table><thead><tr><th>Contact Full Name</th><th>Contact Email Address</th><th></th></tr></thead><tbody>';
                        data.linked_contacts.forEach(contact => {
                            table += `<tr>
                                <td>${sanitizeHTML(contact.surname + contact.name)}</td>
                                <td>${sanitizeHTML(contact.email)}</td>
                                <td><a href="#" onclick="unlinkContact(${clientId}, ${contact.id}); return false;">Unlink</a></td>
                            </tr>`;
                        });
                        table += '</tbody></table>';
                        contactsList.innerHTML = table;
                    }

                    data.available_contacts.forEach(contact => {
                        const option = document.createElement('option');
                        option.value = contact.id;
                        option.textContent = `${contact.surname}${contact.name}`;
                        contactSelect.appendChild(option);
                    });
                })
                .catch(error => {
                    document.getElementById('message').innerHTML = `<p class="error">Error loading contacts: ${error.message}</p>`;
                });
        }

        function unlinkContact(clientId, contactId) {
            fetch(`?action=unlink_contact&client_id=${clientId}&contact_id=${contactId}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! Status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    const messageDiv = document.getElementById('message');
                    if (data.success) {
                        messageDiv.innerHTML = `<p class="success">${data.success}</p>`;
                        loadLinkedContacts(clientId);
                    } else {
                        messageDiv.innerHTML = `<p class="error">${data.error || 'Unknown error occurred.'}</p>`;
                    }
                })
                .catch(error => {
                    document.getElementById('message').innerHTML = `<p class="error">Error: ${error.message}</p>`;
                });
        }

        function loadLinkedClients(contactId) {
            const clientsList = document.getElementById('clients_list');
            const clientSelect = document.getElementById('client_id');
            if (!clientsList || !clientSelect) return;

            fetch(`?action=get_linked_clients&contact_id=${contactId}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! Status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    clientsList.innerHTML = '';
                    clientSelect.innerHTML = '<option value="">Select Client</option>';

                    if (data.error) {
                        clientsList.innerHTML = `<p class="error">${data.error}</p>`;
                        return;
                    }

                    if (data.linked_clients.length === 0) {
                        clientsList.innerHTML = '<p>No client(s) found.</p>';
                    } else {
                        let table = '<table><thead><tr><th>Client Name</th><th>Client Code</th><th></th></tr></thead><tbody>';
                        data.linked_clients.forEach(client => {
                            table += `<tr>
                                <td>${sanitizeHTML(client.name)}</td>
                                <td>${sanitizeHTML(client.client_code)}</td>
                                <td><a href="#" onclick="unlinkClient(${contactId}, ${client.id}); return false;">Unlink</a></td>
                            </tr>`;
                        });
                        table += '</tbody></table>';
                        clientsList.innerHTML = table;
                    }

                    data.available_clients.forEach(client => {
                        const option = document.createElement('option');
                        option.value = client.id;
                        option.textContent = client.name;
                        clientSelect.appendChild(option);
                    });
                })
                .catch(error => {
                    document.getElementById('message').innerHTML = `<p class="error">Error loading clients: ${error.message}</p>`;
                });
        }

        function unlinkClient(contactId, clientId) {
            fetch(`?action=unlink_client&contact_id=${contactId}&client_id=${clientId}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! Status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    const messageDiv = document.getElementById('message');
                    if (data.success) {
                        messageDiv.innerHTML = `<p class="success">${data.success}</p>`;
                        loadLinkedClients(contactId);
                    } else {
                        messageDiv.innerHTML = `<p class="error">${data.error || 'Unknown error occurred.'}</p>`;
                    }
                })
                .catch(error => {
                    document.getElementById('message').innerHTML = `<p class="error">Error: ${error.message}</p>`;
                });
        }

        // Basic HTML sanitization to prevent XSS
        function sanitizeHTML(str) {
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }
    </script>
</body>
</html>
