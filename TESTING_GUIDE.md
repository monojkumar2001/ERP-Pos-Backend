# 🧪 Events Management System - Testing Guide

## ✅ Pre-Flight Checks

### Database

```bash
# Check if events table exists
php artisan tinker
>>> Schema::hasTable('events')
>>> DB::table('events')->count()
```

### Controller Syntax

```bash
php -l app/Http/Controllers/Admin/EventsController.php
# Output: No syntax errors detected
```

---

## 📋 Manual Testing Steps

### Test 1: Create Event (Without Image)

**Steps:**

1. Go to Admin Panel → Events → Create Event
2. Fill in:

    - Title (EN): "My First Event"
    - Title (BN): "আমার প্রথম ইভেন্ট"
    - Slug: (auto-generated or manual)
    - Event Date: Select any date
    - Event Time: Select any time
    - Location (EN): "Dhaka"
    - Short Description: "Brief desc"
    - Leave image empty
    - Status: Active

3. Click "Create"
4. **Expected:** Event created successfully, redirected to list

---

### Test 2: Create Event (With Image)

**Steps:**

1. Go to Admin Panel → Events → Create Event
2. Fill all fields as above
3. Upload an image (JPG, PNG, GIF, WebP, max 5MB)
4. Click "Create"
5. **Expected:**
    - Image converted to WebP (if possible)
    - Saved in `public/uploads/events/`
    - Database record created with image path

---

### Test 3: Update Event (No Image Change)

**Steps:**

1. Go to Events list
2. Click Edit on any event
3. Change Title or Description only
4. Leave image field empty
5. Click "Update"
6. **Expected:**
    - Event updated
    - Original image preserved
    - No new files created

---

### Test 4: Update Event (Replace Image)

**Steps:**

1. Go to Events list
2. Click Edit on an event with image
3. Note the existing image
4. Upload a new image
5. Click "Update"
6. **Expected:**
    - Old image deleted from filesystem
    - New image saved
    - Database updated with new image path

---

### Test 5: Slug Uniqueness on Update

**Steps:**

1. Create Event A with slug: "event-a"
2. Create Event B with slug: "event-b"
3. Edit Event B
4. Try to change slug to "event-a" (duplicate)
5. Click "Update"
6. **Expected:**
    - Slug changed to "event-a-1" (auto-incremented)
    - Or validation error (depending on implementation)

---

### Test 6: Delete Event

**Steps:**

1. Go to Events list
2. Click Delete on event with image
3. Confirm deletion
4. **Expected:**
    - Event deleted from database
    - Image file deleted from filesystem
    - Redirected to events list

---

## 🔍 Database Checks

### After Create

```sql
SELECT * FROM events WHERE title_en = 'My First Event';
-- Check all fields populated correctly
-- image field should have path or null
-- slug should be unique
-- status should be 1 or 0
```

### After Update

```sql
SELECT * FROM events WHERE id = 1;
-- Check updated fields
-- image should be same (if not replaced) or new path (if replaced)
-- timestamps should be updated (updated_at)
```

### After Delete

```sql
SELECT * FROM events;
-- Check event is gone
-- Verify image file deleted from disk
```

---

## 📂 File System Checks

### Image Location

```
public/uploads/events/
├── 1731428234_1234567.webp
├── 1731428301_7654321.jpg
└── ...
```

### Verify Image Deleted

```bash
# After updating event with new image
ls -la public/uploads/events/
# Old image should be gone
```

---

## 🐛 Common Issues & Solutions

### Issue: Image not uploading

**Solutions:**

-   Check `public/uploads/events/` directory has write permission (755)
-   Check file size < 5MB
-   Check allowed formats: jpeg, png, jpg, gif, webp

### Issue: Slug not auto-generated

**Solutions:**

-   Check title_en field has value
-   JavaScript must be enabled (client-side slug generation)
-   Manual slug entry should work as fallback

### Issue: Old image not deleted

**Solutions:**

-   Check file permissions
-   Verify path format (should start with `uploads/`)
-   Check storage disk configuration

### Issue: WebP conversion fails

**Solutions:**

-   System will fallback to original format
-   Check `imagewebp()` function available
-   Check error logs: `storage/logs/laravel.log`

---

## 📊 Performance Checks

### Image Upload Performance

-   Single image upload: < 2 seconds
-   WebP conversion: < 1 second
-   Database insert: < 0.5 seconds

### List View Performance

-   Loading 100 events: < 1 second
-   Pagination working: Yes

---

## ✅ Checklist Before Production

-   [ ] Database migrated: `php artisan migrate`
-   [ ] Uploads directory created: `public/uploads/events/`
-   [ ] Directory writable: `chmod 755 public/uploads/events`
-   [ ] All PHP functions available: `imagewebp()`, `imagecreatefromstring()`
-   [ ] Validation errors display properly
-   [ ] Images served correctly from `asset()` helper
-   [ ] Slug auto-generation works
-   [ ] Delete confirmation works
-   [ ] All CRUD operations working
-   [ ] Error logs clear

---

## 🚀 Browser Compatibility

-   ✅ Chrome/Edge (WebP support)
-   ✅ Firefox (WebP support)
-   ✅ Safari (WebP limited, falls back to original)
-   ✅ Mobile browsers

---

## 📝 Logs to Check

```bash
# Check for any errors
tail -f storage/logs/laravel.log

# Search for image processing errors
grep -i "image processing error" storage/logs/laravel.log
grep -i "webp conversion" storage/logs/laravel.log
```

---

## 🎯 API Testing (if applicable)

For REST API endpoints, use Postman/Insomnia:

### Create Event (POST)

```
POST /api/admin/events
Content-Type: application/json

{
  "title_en": "Event Name",
  "slug": "event-name",
  "event_date": "2025-11-15",
  "event_time": "10:00",
  "status": true
}
```

### Update Event (PUT)

```
PUT /api/admin/events/{id}
Content-Type: application/json

{
  "title_en": "Updated Event",
  "status": false
}
```

### Delete Event (DELETE)

```
DELETE /api/admin/events/{id}
```

---

**Last Updated:** November 13, 2025
**Status:** Ready for Testing ✅
