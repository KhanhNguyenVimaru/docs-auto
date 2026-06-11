# DocFormatter - DOCX Style Reformatter & Validator

[![PHP Version](https://img.shields.io/badge/php-%5E8.3-blue.svg)](https://www.php.net/)
[![Laravel Version](https://img.shields.io/badge/laravel-%5E13.0-red.svg)](https://laravel.com)
[![Vite](https://img.shields.io/badge/vite-%5E5.0-purple.svg)](https://vitejs.dev)
[![Docker](https://img.shields.io/badge/docker-supported-blue.svg)](https://www.docker.com/)

DocFormatter là một ứng dụng web mạnh mẽ được phát triển trên nền tảng **Laravel** (Backend) và **Vite/Tailwind CSS v4** (Frontend) giúp tự động hóa quá trình định dạng, kiểm tra (validate) và xuất các tài liệu Word (`.docx`) dựa trên các Template mẫu tiêu chuẩn.

*Để xem phiên bản Tiếng Anh, vui lòng truy cập [README.md](file:///c:/Users/admin/docs-gpt/README.md).*

---

## Tính Năng Nổi Bật

* **Định dạng tự động (Auto Formatting)**:
  * Căn chỉnh lề trang (Top, Bottom, Left, Right) chính xác theo Template.
  * Tự động thụt lề đầu dòng bằng ký tự Tab (`\t`) cho các đoạn văn thông thường (không áp dụng cho tiêu đề).
  * Tự động căn chỉnh khoảng cách dòng (Line Spacing) và khoảng cách sau đoạn văn (`spaceAfter`) một cách động.
  * Tự động đánh số thứ tự tiêu đề (Heading Levels từ 1 đến 4) nếu được kích hoạt.
* **Xử lý hình ảnh thông minh (Smart Image Processing)**:
  * Tự động tính toán chiều rộng vùng in khả dụng dựa trên kích thước lề trang của template.
  * Thu nhỏ (Scale) các hình ảnh có kích thước lớn để vừa khít với văn bản mà vẫn giữ nguyên tỷ lệ (Aspect Ratio) gốc.
  * Tự động căn lề giữa cho các hình ảnh có chiều rộng nhỏ hơn vùng in.
  * Loại bỏ hoàn toàn thụt lề bằng ký tự tab đầu dòng đối với các đoạn có chứa hình ảnh.
* **Tự động định dạng danh sách (Bullet Lists)**:
  * Tích hợp sâu vào DOCX engine giúp thu nhỏ kích thước chấm tròn (bullet points) về size **8pt** (16 half-points) chuyên nghiệp.
  * Loại bỏ khoảng cách cách đoạn trên/dưới của các list item (`spaceAfter = 0`, `spaceBefore = 0`) và thiết lập giãn dòng đơn (`lineHeight = 1.0`).
* **Báo cáo kiểm tra chất lượng (Validation Report)**:
  * Quét tài liệu và chỉ ra cụ thể các điểm không tuân thủ định dạng tiêu chuẩn (Font chữ, cỡ chữ, căn lề, thụt dòng, khoảng cách đoạn, kích thước ảnh, danh sách...).
* **Khung xem trước trực quan (Live Preview)**:
  * Hiển thị trực quan nội dung tài liệu ngay trên giao diện Web theo chuẩn trang A4 trước khi người dùng thực hiện tải xuống.

---

## Hướng Dẫn Cài Đặt và Khởi Chạy

### Cách 1: Sử dụng Docker & Docker Compose (Khuyên dùng - Mở là chạy luôn)

Yêu cầu máy tính cài đặt sẵn Docker và Docker Compose. Bạn chỉ cần chạy lệnh sau tại thư mục gốc:

```bash
# Khởi động các container ở chế độ background và tự động build
docker-compose up -d --build
```

* Hệ thống sẽ tự động cài đặt các thư viện PHP/Node.js, build các file giao diện tĩnh (Vite), khởi tạo MySQL, tự động chạy migrations, seeders dữ liệu và tạo storage link.
* Truy cập ứng dụng tại địa chỉ: **[http://localhost:8000](http://localhost:8000)**
* Để tắt ứng dụng: `docker-compose down`

---

### Cách 2: Cài đặt Thủ công (Local Environment)

#### Yêu cầu hệ thống
* PHP >= 8.3 (yêu cầu cài đặt các PHP Extension: `gd`, `zip`, `pdo_mysql`, `mbstring`, `xml`, `bcmath`).
* Composer.
* Node.js (phiên bản 18 hoặc mới hơn) & NPM.
* Một cơ sở dữ liệu MySQL trống (hoặc SQLite).

#### Các bước thực hiện:

1. **Cài đặt thư viện Backend**:
   ```bash
   composer install
   ```

2. **Cấu hình môi trường**:
   Sao chép file cấu hình và cập nhật thông tin kết nối cơ sở dữ liệu của bạn trong `.env`:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

3. **Cài đặt cơ sở dữ liệu**:
   ```bash
   php artisan migrate --seed
   php artisan storage:link
   ```

4. **Cài đặt thư viện Frontend & Build assets**:
   ```bash
   npm install
   npm run build
   ```

5. **Khởi chạy Server**:
   Để chạy ứng dụng ở môi trường phát triển (Local Development) hỗ trợ Hot Reload:
   ```bash
   npm run dev
   ```
   *Lệnh trên sẽ tự động khởi động máy chủ Laravel (Artisan serve) và máy chủ Vite phát triển cùng một lúc.*
   *Truy cập ứng dụng tại: **[http://localhost:8000](http://localhost:8000)***

---

## Cấu Trúc Dự Án Chính

* [DocumentProcessingService.php](file:///c:/Users/admin/docs-gpt/app/Services/DocumentProcessingService.php): Engine lõi xử lý định dạng tài liệu, co giãn hình ảnh, quản lý bullet list, và chạy bộ quét lỗi định dạng (Validation).
* [DocumentFormatHelper.php](file:///c:/Users/admin/docs-gpt/app/Helpers/DocumentFormatHelper.php): Các hàm tiện ích chuyển đổi đơn vị và xây dựng style cho PHPWord.
* [Numbering.php](file:///c:/Users/admin/docs-gpt/vendor/phpoffice/phpword/src/PhpWord/Writer/Word2007/Part/Numbering.php): Lớp mở rộng ghi cấu trúc XML tùy chỉnh size 8pt cho bullet points.
* [index.blade.php](file:///c:/Users/admin/docs-gpt/resources/views/index.blade.php): Giao diện người dùng đơn trang (Single Page UI) được tối ưu hóa đẹp mắt với Tailwind CSS v4 và DaisyUI.
