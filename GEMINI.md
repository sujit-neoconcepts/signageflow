# Gemini Project Instructions

This document provides instructions for interacting with the SignageFlow project.

## Project Overview

SignageFlow is a web application built with the TALL stack:

-   **Laravel 12:** A PHP web application framework.
-   **Vue.js:** A progressive JavaScript framework for building user interfaces.
-   **Inertia.js:** A tool that connects a server-side framework (Laravel) to a client-side framework (Vue.js) without building an API.
-   **Tailwind CSS:** A utility-first CSS framework.
-   **Vite:** A build tool that provides a faster and leaner development experience for modern web projects.

## Development Environment

### Prerequisites

-   PHP 8.2 or higher
-   Node.js and npm/pnpm/yarn
-   Composer

### Installation

1. **Clone the repository:**
    ```bash
    git clone git@github.com:sujit-neoconcepts/signageflow.git
    ```
2. **Install PHP dependencies:**
    ```bash
    composer install
    ```
3. **Install Node.js dependencies:**
    ```bash
    pnpm install
    ```
4. **Create a copy of the `.env.example` file and name it `.env`:**
    ```bash
    cp .env.example .env
    ```
5. **Generate an application key:**
    ```bash
    php artisan key:generate
    ```
6. **Configure your database credentials in the `.env` file.**
7. **Run the database migrations:**
    ```bash
    php artisan migrate
    ```

### Running the Development Server

-   **Start the Vite development server:**
    ```bash
    pnpm  dev
    ```
    *Note: `pnpm dev` runs continuously during development to compile assets. You do not need to run `pnpm build` manually.*
-   **Start the Laravel development server:**
    ```bash
    php artisan serve
    ```

## Coding Conventions

-   **PHP:** Follow the PSR-12 coding style guide.
-   **JavaScript:** Use the standard JavaScript style.
-   **Vue:** Follow the official Vue.js style guide.
-   **Tailwind CSS:** Use the utility-first approach.

## Testing

-   **Run the test suite:**
    ```bash
    php artisan tinker
    ```

## Database Migrations

-   **NEVER** delete migration files from the `database/migrations` directory. They are essential for the project's history and for other developers.
-   **NEVER** run `php artisan migrate:fresh` or `php artisan migrate:refresh` on a development or production database, as it will delete all existing data. Use these commands only for initial setup or when you are certain that data loss is not an issue.
