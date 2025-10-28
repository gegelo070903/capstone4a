# 🏗️ PHP Construction Project Management System

A **Construction Project Management System** built with **PHP**, **MySQL**, **HTML**, and **CSS (no Bootstrap)**.  
Designed to help administrators and constructors **create, track, and manage construction projects** — including project units, materials, and daily reports — in a modern, minimal dashboard.

---

## 🚀 Features

### 👷 Core Modules
| Module | Description |
| ------- | ------------ |
| **Projects** | Create, edit, and delete construction projects. |
| **Development** | Track project progress and manage unit phases. |
| **Reports** | Record and view daily construction updates. |
| **Materials** | Log materials used for each project. |
| **Authentication** | Role-based access (`admin`, `constructor`). |

---

## 🧱 Database Schema

### `projects`

| Field | Type | Description |
| ------ | ----- | ----------- |
| id | INT (AI, PK) | Project ID |
| name | VARCHAR(100) | Project name |
| location | VARCHAR(100) | Project location |
| units | INT | Number of housing units |
| progress | INT | Overall progress (%) |
| status | ENUM('Pending', 'Ongoing', 'Completed') | Current status |
| created_at | DATETIME | Creation timestamp |
| folder_path | VARCHAR(255) | Optional file storage path |

### `project_units`

| Field | Type | Description |
| ------ | ----- | ----------- |
| id | INT (AI, PK) | Unit ID |
| project_id | INT (FK) | Linked project |
| name | VARCHAR(100) | e.g. “Unit 1” |
| description | TEXT | Unit description |
| progress | INT | Progress percentage |
| created_at | DATETIME | Creation timestamp |

---

## ⚙️ Functional Requirements

1. **Admin-Only Project Creation**
   - Form inputs: `name`, `location`, `units`, `status`
   - Auto-generates unit records upon creation (e.g., 3 units → `Unit 1`, `Unit 2`, `Unit 3`)
   - Redirects to `view_project.php?id={project_id}` on success

2. **Self-Contained Logic**
   - `add_project.php` combines both **form display** and **processing logic**

3. **Security**
   - Includes `require_login()` and `is_admin()` checks
   - All SQL queries use **prepared statements**
   - Timezone set to `Asia/Manila`

4. **UI / UX**
   - Pure CSS — no Bootstrap
   - Uses CSS variables:
     ```css
     :root {
       --brand-blue: #2563eb;
       --gray-light: #f3f4f6;
     }
     ```
   - Responsive grid layout (desktop / tablet / mobile)
   - Smooth fade-in animations and card shadows

---

## 🧩 Project Dashboard

- Sticky header with:
  - Search bar
  - Sort dropdown (Latest, Oldest, A–Z, Z–A)
  - “+ Add Project” button (modal overlay)
- Project cards shown in a **3×3 responsive grid**
- Status badges:
  - 🟡 Pending → `#fbbf24`
  - 🔵 Ongoing → `#3b82f6`
  - 🟢 Completed → `#22c55e`

---

## 🗂️ File Overview

| File | Description |
| ---- | ------------ |
| `index.php` | Dashboard with project grid |
| `add_project.php` | Unified form + logic for adding projects |
| `view_project.php` | Project details, unit tracking, materials, and reports |
| `includes/db_connect.php` | Database connection using MySQLi |
| `includes/functions.php` | Authentication and utility functions |
| `assets/css/style.css` | Global styling with animations |

---

## 💾 Installation

1. Clone the repository:
   ```bash
   git clone https://github.com/gegelo070903/capstone4a.git
