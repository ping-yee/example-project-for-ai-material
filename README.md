# Todo Project

The example code for AI Material.

## Getting Started

1. **Copy environment configuration**
   ```bash
   cp .env.example .env
   ```

2. **Start Docker containers**
   ```bash
   docker compose up
   ```

3. **PHP env install**
   ```bash
   /bin/bash -c "$(curl -fsSL https://php.new/install/mac/8.4)"
   ```

4. **Run database migrations**
   ```bash
   php artisan migrate
   ```

5. **Start development server**
   ```bash
   composer run dev
   ```

6. **Open browser and visit**
   ```
   http://127.0.0.1:8000/todo
   ```

## Tech Stack

- **Backend**: Laravel 12 (PHP)
- **Database**: MySQL
- **Containerization**: Docker
- **Frontend**: Blade templating engine

## System Requirements

- PHP 8.3+
- Composer
- Docker & Docker Compose
