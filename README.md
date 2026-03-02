# TheArchive

A modern web-based archive viewer for media with thumbnail generation, user profiles, and async processing.

![License](https://img.shields.io/badge/license-MIT-blue.svg)
![Docker](https://img.shields.io/badge/docker-ready-brightgreen.svg)
![PHP](https://img.shields.io/badge/php-8.2-blue.svg)

## Features

- 📁 **Archive Browsing** - Browse user folders with images and videos
- 🖼️ **Thumbnail Generation** - Async thumbnail generation with progress tracking
- 👤 **User Profiles** - Enhanced profiles with avatars, URLs, and location
- ⚙️ **Modern Settings** - Tabbed settings interface
- 🔒 **Secure** - Password protection and session management
- 🚀 **Fast** - Optimized thumbnails (200px) and batch processing
- 📱 **Mobile Friendly** - Responsive design

## Quick Start

### Using Docker (Recommended)

```bash
docker run -d \
  -p 60055:80 \
  -v ./data:/var/www/html/data \
  -v /path/to/archive:/var/www/html/data/archive \
  404breakdown/thearchive:latest
```

Or with docker-compose:

```yaml
version: '3.8'
services:
  thearchive:
    image: 404breakdown/thearchive:latest
    ports:
      - '60055:80'
    restart: unless-stopped
    volumes:
      - ./data:/var/www/html/data
      - /path/to/your/archive:/var/www/html/data/archive
```

Then visit: `http://localhost:60055/setup.php`

### Building from Source

```bash
# Clone the repository
git clone https://github.com/404breakdown/thearchive.git
cd thearchive

# Build the Docker image
docker build -t thearchive:latest .

# Run it
docker-compose up -d
```

## Archive Structure

Your archive folder should be organized as:

```
archive/
├── Username1/
│   ├── Posts/
│   │   ├── Images/
│   │   │   ├── photo1.jpg
│   │   │   └── photo2.png
│   │   └── Videos/
│   │       └── video1.mp4
│   └── profile/
│       └── Images/
│           └── avatar.jpg
└── Username2/
    └── ...
```

**Supported structures:**
- `Username/Images/` and `Username/Videos/`
- `Username/Posts/Images/` and `Username/Posts/Videos/`
- `Username/profile/Images/` (used as avatar)

All folder names are case-insensitive.

## Configuration

### Environment Variables

- `TZ` - Timezone (default: UTC)

### Volumes

- `/var/www/html/data` - Application data (database, sessions)
- `/var/www/html/data/archive` - Your media archive

### Ports

- `80` - Web interface

## First Time Setup

1. Visit `http://your-server:60055/setup.php`
2. Create admin account
3. Configure site name
4. Delete `setup.php` for security

## Features Overview

### Dashboard
- View total users, images, videos
- See storage usage
- Quick links to archive

### Archive Browser
- Browse all archived users
- View images and videos
- Generate thumbnails on-demand

### User Profiles
- Display name and avatar
- Location with map icon
- Up to 3 custom URLs
- Notes section
- Image/video counts

### Settings
**General** - Site name, archive path
**Security** - Change admin password
**Thumbnails** - Generate all, clear cache, view stats
**System** - PHP, GD, FFmpeg status

### Thumbnail Generation
- Async batch processing (10 at a time)
- Live progress tracking
- 200px optimized size
- Quality 70 for smaller files
- Automatic regeneration on view

## Development

### Requirements
- PHP 8.2+
- SQLite3
- GD Library
- FFmpeg (for video thumbnails)

### Local Development

```bash
# Clone repo
git clone https://github.com/404breakdown/thearchive.git
cd thearchive

# Run with mounted source for live editing
docker run -d -p 60055:80 \
  -v $(pwd):/var/www/html \
  -v ./data:/var/www/html/data \
  php:8.2-apache
```

### Building

```bash
# Build image
docker build -t 404breakdown/thearchive:latest .

# Push to Docker Hub
docker login
docker push 404breakdown/thearchive:latest
```

## Tech Stack

- **Backend:** PHP 8.2, SQLite
- **Frontend:** Bootstrap 5, Bootstrap Icons
- **Server:** Apache
- **Image Processing:** GD Library
- **Video Processing:** FFmpeg
- **Containerization:** Docker

## API Endpoints

- `generate_thumbs.php` - Async thumbnail generation
- `get_users.php` - List archive users

## Security Notes

- Delete `setup.php` after initial setup
- Use strong admin password
- Database stored in `/data` (not web accessible)
- Session management with PHP sessions
- SQL injection protection via PDO prepared statements

## Contributing

Pull requests are welcome! For major changes, please open an issue first.

## License

MIT License - feel free to use and modify!

## Support

- **Issues:** https://github.com/404breakdown/thearchive/issues
- **Docker Hub:** https://hub.docker.com/r/404breakdown/thearchive

## Changelog

### v1.0.0
- Initial release
- Archive browsing
- Thumbnail generation
- User profiles
- Settings page

## Screenshots

### Dashboard
![Dashboard](https://via.placeholder.com/800x400?text=Dashboard+Screenshot)

### Archive Browser
![Archive](https://via.placeholder.com/800x400?text=Archive+Screenshot)

### User Profile
![Profile](https://via.placeholder.com/800x400?text=Profile+Screenshot)

---

**Made with ❤️ by 404breakdown**
