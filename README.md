# Library-System
Web System and Technologies 2 project "Library System - Heroes"


This project is a web-based Library Management System created for the Web System and Technologies 2 course. It allows students to register for an account, log in, and view the library's collection. The system is built using fundamental web technologies, featuring a PHP backend to handle user authentication and a MySQL database to store user data.

---

## Setup and Installation Instructions

To run this application locally, you will need to have a web server environment like XAMPP.

1.  **Install XAMPP:** Download and install XAMPP from the [official Apache Friends website](https://www.apachefriends.org/index.html).

2.  **Start Services:** Open the XAMPP Control Panel and start the **Apache** and **MySQL**.

3.  **Place Project Files:**
    *   Navigate to your XAMPP installation directory (e.g., `C:\xampp`).
    *   Open the `htdocs` folder.
    *   Place the entire `Library-System-main` project folder inside `htdocs`.

4.  **Import the Database:**
    *   Open your web browser and go to `http://localhost/phpmyadmin/`.
    *   Create a new database and name it `jasper`.
    *   Select the `jasper` database and go to the "Import" tab.
    *   Click "Choose File" and select the `.sql` file located in the `/database/` folder of this project.
    *   Click "Go" to import the table structure.

5.  **Run the Application:**
    *   Open your web browser and navigate to: `http://localhost/Library-System-main/frontend/`
    *   The application should now be running.

---

## Technologies Used

### Front-End
*   **HTML5:** Used for the structure and content of the web pages.
*   **CSS3:** Used for styling the user interface.
*   **JavaScript:** Used for client-side interactivity, such as the login/signup modal and tab switching.

### Back-End
*   **PHP:** Used for all server-side logic, including user registration, login, logout, and database communication.

### Database
*   **MySQL:** Used to store all user account information.

### Environment and Tools
*   **XAMPP:** Used as the local development environment (includes Apache server and MySQL).
*   **phpMyAdmin:** Used for managing the MySQL database.
*   **Visual Studio Code:** The primary code editor for this project.
*   **Git & GitHub:** Used for version control and code hosting.****
