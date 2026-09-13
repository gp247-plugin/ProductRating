> 🌐 **Ngôn ngữ:** 🇻🇳 Tiếng Việt (hiện tại) · [🇬🇧 English](./readme.md)

# Đánh giá sản phẩm (ProductRating)

## Giới thiệu

Plugin này cho phép khách hàng chấm điểm và viết nhận xét cho sản phẩm trên
website bán hàng GP247, đồng thời cho bạn — chủ shop — công cụ để kiểm duyệt và
trả lời những đánh giá đó. Tài liệu dành cho người quản trị cửa hàng (không cần
biết lập trình); phần cuối có thêm hướng dẫn riêng cho người làm giao diện và lập
trình viên. Đọc xong bạn sẽ cài được plugin, hiểu mình bật/tắt được những gì, và
biết ai có quyền làm gì với một đánh giá.

## Tính năng chính

- Khách **phải đăng nhập** mới đánh giá được.
- Bạn chọn: **chỉ khách đã mua** mới được đánh giá, hay ai cũng được.
- Bạn chọn: đánh giá mới **phải duyệt** trước khi hiện, hay hiện ngay.
- Cho phép khách **đính kèm ảnh** (bật/tắt, giới hạn số ảnh).
- Thang điểm **5 sao hoặc 10 sao**.
- Màn hình **duyệt đánh giá** và màn hình **thống kê theo từng gian hàng**.
- Bạn **trả lời công khai** dưới mỗi đánh giá.
- Khách **tự sửa hoặc gỡ** đánh giá của chính mình.

## Cài đặt

1. Đăng nhập trang quản trị, vào menu **Plugins**.

2. Tìm dòng **Product Rating & Review**, bấm nút **Cài đặt** (Install).

   Nếu thành công, hệ thống hiện thông báo cài đặt thành công và plugin chuyển
   sang trạng thái đã bật.

3. Mở **Terminal** (trên Windows là "Command Prompt"), đi tới thư mục website rồi
   gõ đúng dòng sau và nhấn Enter:

   ```
   php artisan optimize:clear
   ```

   Bước này xoá bộ nhớ đệm để website nhận các màn hình mới. Nếu bỏ qua, bạn có
   thể bấm vào menu mà gặp lỗi "không tìm thấy trang" — đây là lỗi hay gặp nhất
   sau khi cài plugin.

4. Kiểm tra lại: trong trang quản trị, bạn sẽ thấy hai mục menu mới:

   - **Catalog → Đánh giá sản phẩm** (nơi duyệt đánh giá)
   - **Report → Thống kê đánh giá** (nơi xem số liệu)

5. Ra ngoài website, mở một trang sản phẩm bất kỳ và kéo xuống dưới phần mô tả.
   Bạn sẽ thấy khối **"Đánh giá sản phẩm"**.

Khi gỡ cài đặt, plugin tự xoá dữ liệu đánh giá và hai mục menu nói trên.

## Cấu hình

Vào **Plugins**, bấm nút **Cấu hình** ở dòng Product Rating & Review.

| Mục | Mặc định | Ý nghĩa |
| --- | --- | --- |
| Bắt buộc đã mua hàng | Bật | Chỉ khách có đơn chứa sản phẩm mới được đánh giá. |
| Hiển thị ngay | Tắt | Tắt: đánh giá mới phải được bạn duyệt. Bật: hiện ngay. |
| Cho phép đính kèm ảnh | Tắt | Cho khách gửi kèm ảnh thật của sản phẩm. |
| Số ảnh mỗi đánh giá | 3 | Số ảnh tối đa một đánh giá được kèm. |
| Thang điểm | 5 sao | Chọn 5 sao hoặc 10 sao. |

Nếu website của bạn có **nhiều cửa hàng**, mỗi cửa hàng cài đặt riêng được: chọn
cửa hàng ở ô chọn phía trên rồi chỉnh. Cửa hàng chưa chỉnh gì sẽ dùng chung cài
đặt mặc định.

## Khách hàng đánh giá như thế nào

1. Khách mở trang sản phẩm và kéo xuống khối **Đánh giá sản phẩm**.
2. Nếu chưa đăng nhập, khách thấy nút **Đăng nhập** thay cho ô nhập.
3. Khách chọn số sao, viết nhận xét (không bắt buộc) và bấm **Gửi đánh giá**.
4. Nếu bạn để chế độ phải duyệt, khách nhận thông báo đã ghi nhận và đánh giá
   nằm chờ ở màn hình duyệt của bạn.
5. Sau khi gửi, khách thấy lại đánh giá của mình kèm hai nút **Sửa** và
   **Gỡ đánh giá của tôi**.

Đánh giá của khách đã mua hàng có thêm nhãn **"Đã mua hàng"** để người xem tin
tưởng hơn.

## Quản lý đánh giá

Vào **Catalog → Đánh giá sản phẩm**. Bạn lọc được theo trạng thái (chờ duyệt / đã
duyệt / từ chối) và theo cửa hàng, tìm theo nội dung hoặc tên khách.

Bấm **tên sản phẩm** để mở trang sản phẩm đó trên website (tab mới). Với đánh giá
đã duyệt còn có thêm link **Xem trên website** đưa thẳng tới đúng đánh giá đó,
để bạn thấy nó hiện ra sao với người mua.

Với mỗi đánh giá, bạn có bốn thao tác:

- **Duyệt** — đánh giá hiện ra ngoài website và được tính vào điểm trung bình.
- **Từ chối** — đánh giá không hiện ra ngoài nữa. Bạn **bắt buộc chọn lý do** từ
  danh sách có sẵn (xem mục dưới).
- **Trả lời công khai** — câu trả lời của bạn hiện ngay dưới đánh giá đó, mọi
  người xem sản phẩm đều thấy. Mỗi đánh giá một câu trả lời, sửa lại được; lưu
  nội dung rỗng thì gỡ câu trả lời.
- **Xoá** — xoá hẳn. **Chỉ tài khoản quản trị hệ thống mới làm được**; chủ shop
  và nhân viên không thấy nút này.

### Vì sao chủ shop không được xoá đánh giá

Nếu shop tự xoá được những đánh giá chê mình thì điểm sao chỉ còn là quảng cáo,
người mua không còn tin được nữa. Hai cơ quan quản lý lớn đã quy định trực tiếp
việc này:

- **Mỹ (FTC, hiệu lực 21/10/2024):** không được trưng khu vực đánh giá như thể
  đang hiển thị tất cả, trong khi âm thầm chặn bớt những đánh giá **vì nó cho
  điểm thấp hoặc có ý chê**. Gỡ vẫn hợp lệ nếu tiêu chí áp dụng **như nhau cho
  mọi đánh giá**, bất kể khen hay chê.
- **Châu Âu (Chỉ thị 2019/2161, áp dụng 28/5/2022):** cấm xuyên tạc đánh giá của
  người tiêu dùng để quảng bá sản phẩm.

Vì vậy plugin thiết kế như sau: bạn vẫn gỡ được một đánh giá, nhưng phải **nêu lý
do** từ một danh sách cố định, và **mọi quyết định đều được ghi nhật ký** (ai làm,
lúc nào, lý do gì). Công cụ dành cho bạn khi gặp đánh giá chê là **trả lời công
khai**, không phải xoá đi.

Danh sách lý do từ chối: spam hoặc đánh giá giả · xúc phạm, thù ghét · chứa thông
tin cá nhân của người khác · nội dung vi phạm pháp luật · xung đột lợi ích · không
nói về sản phẩm này. Cố ý **không có** lý do "điểm thấp".

## Thống kê

Vào **Report → Thống kê đánh giá**. Màn hình này cho bạn:

- Tổng số đánh giá, số đã hiển thị, số đang chờ duyệt, điểm trung bình.
- Bảng so sánh **theo từng gian hàng** (hữu ích khi website có nhiều cửa hàng
  hoặc nhiều người bán).
- Biểu đồ phân bố điểm: bao nhiêu đánh giá 5 sao, 4 sao…
- Danh sách sản phẩm được đánh giá nhiều nhất kèm điểm trung bình.

Điểm trung bình **chỉ tính đánh giá đã duyệt**. Đánh giá đang chờ hoặc bị từ chối
không bao giờ ảnh hưởng điểm hiển thị ra ngoài.

## Đưa khối đánh giá sang giao diện khác (cho người làm giao diện)

Plugin tự động hiện khối đánh giá trên giao diện **GP247Front**. Nếu bạn dùng
giao diện khác, mở file trang chi tiết sản phẩm của giao diện đó và thêm đúng
đoạn sau vào vị trí bạn muốn:

```blade
@if (function_exists('gp247_product_rating_enabled') && gp247_product_rating_enabled())
    @livewire('gp247-productrating-front::review-box', ['productId' => $product->id], key('product-review-'.$product->id))
@endif
```

Muốn đổi cách hiển thị mà không sửa plugin: tạo file
`livewire/productrating_review-box.blade.php` trong thư mục giao diện của bạn.
Hệ thống sẽ ưu tiên file của bạn, và bản gốc trong plugin vẫn được giữ nguyên khi
cập nhật.

## Gắn đánh giá vào trang gian hàng (cho lập trình viên)

Mỗi đánh giá ghi nhớ **hai** cửa hàng: nơi khách viết đánh giá, và **cửa hàng bán
sản phẩm**. Trên website một cửa hàng, hai giá trị này giống nhau. Trên mô hình
sàn nhiều người bán dùng chung một tên miền, chúng khác nhau — và cửa hàng bán
sản phẩm mới là thứ trang gian hàng cần gom nhóm theo.

Khối dựng sẵn cho trang gian hàng (có điểm trung bình, bộ lọc theo số sao, danh
sách sản phẩm được đánh giá và các nhận xét):

```blade
@livewire('gp247-productrating-front::store-rating-box', ['sellerStoreId' => $store->id])
```

Muốn tự dựng giao diện thì gọi các hàm sau:

```php
// Số liệu tổng của gian hàng: tổng, trung bình, phân bố theo sao.
$summary = gp247_product_rating_store_summary($sellerStoreId);

// Gian hàng được đánh giá ở những sản phẩm nào, nhiều nhận xét trước.
$products = gp247_product_rating_store_products($sellerStoreId, limit: 20, offset: 0);

// Một lần gọi cho cả lưới sản phẩm (không bị chậm vì gọi lặp).
$map = gp247_product_rating_bulk_summary($productIds, $sellerStoreId);

// Số liệu của một sản phẩm, như trang chi tiết đang dùng.
$summary = gp247_product_rating_summary($productId, $storeId);
```

Tất cả chỉ đếm đánh giá **đã duyệt**.

## Điều kiện & ràng buộc (hiểu trước khi thao tác)

### Khi khách gửi đánh giá

- **Phải đăng nhập** — đây là quy tắc cứng, không tắt được. Đánh giá nặc danh là
  cửa ngõ của spam.
- **Phải chấm điểm; chỉ được viết chữ không thì không gửi được** — điểm là thứ
  tạo ra đánh giá trung bình, nếu thiếu thì nhận xét không đóng góp gì cho người
  mua đang cân nhắc.
- **Điểm phải nằm trong thang đã chọn** (1–5 hoặc 1–10). Hệ thống kiểm tra lại ở
  máy chủ, không chỉ ở màn hình.
- **Nhận xét tối đa 2.000 ký tự.**
- **Mỗi đơn hàng được một đánh giá** — mua 2 lần thì viết được 2 đánh giá, nhưng
  một lần mua không thể thành hai điểm. Đây là cách chặn việc một người tự đẩy
  điểm sản phẩm lên hoặc dìm xuống.
- **Nếu bật "Bắt buộc đã mua hàng"**, khách phải có đơn chứa sản phẩm đó. Đơn đã
  **huỷ** hoặc **thất bại** không tính — nếu tính thì chỉ cần đặt rồi huỷ là
  đánh giá được.
- **Ảnh:** chỉ nhận JPG, PNG, WEBP, mỗi ảnh tối đa 2 MB, số lượng theo cài đặt
  của bạn.

### Khi khách sửa hoặc gỡ đánh giá của mình

- **Sửa xong sẽ quay lại trạng thái chờ duyệt** (trừ khi bạn bật "Hiển thị ngay")
  — nếu không, một đánh giá đã duyệt có thể bị viết lại thành nội dung khác mà
  không ai xem lại.
- **Gỡ rồi không viết lại được cho đơn đó** — nội dung và ảnh bị xoá thật, nhưng
  suất đánh giá của đơn vẫn coi như đã dùng. Nếu không làm vậy, gỡ-rồi-viết-lại
  sẽ thành cách chấm điểm lại không giới hạn.

### Khi bạn kiểm duyệt

- **Từ chối bắt buộc chọn lý do** từ danh sách cố định; không chọn thì hệ thống
  không cho gỡ. Lý do để trống hoặc tự nghĩ ra đều bị chặn.
- **Trả lời công khai tối đa 1.000 ký tự**, và trả lời **không làm thay đổi**
  việc đánh giá đó có được hiển thị hay không.
- **Chỉ quản trị hệ thống mới xoá được** đánh giá. Chủ shop và nhân viên chỉ
  duyệt, từ chối và trả lời.

## Hỏi & Đáp (Q&A)

**Câu 1: Tôi cài xong nhưng bấm menu thì báo lỗi không tìm thấy trang?**

→ Bạn chưa chạy `php artisan optimize:clear` ở Bước 3 phần Cài đặt. Chạy lại lệnh đó rồi tải lại trang.

**Câu 2: Trang sản phẩm không thấy khối đánh giá đâu cả?**

→ Kiểm tra plugin đã bật chưa trong menu Plugins. Nếu website dùng giao diện không phải GP247Front, bạn cần thêm đoạn mã ở mục "Đưa khối đánh giá sang giao diện khác".

**Câu 3: Tôi muốn khách nào cũng đánh giá được, không cần mua hàng?**

→ Vào Cấu hình plugin, tắt mục "Bắt buộc đã mua hàng". Lúc đó mỗi khách được một đánh giá cho mỗi sản phẩm.

**Câu 4: Vì sao tôi không thấy nút xoá đánh giá?**

→ Vì tài khoản của bạn không phải quản trị hệ thống. Chủ shop và nhân viên chỉ được duyệt, từ chối và trả lời — xem mục "Vì sao chủ shop không được xoá đánh giá".

**Câu 5: Có đánh giá 1 sao nói sai sự thật, tôi phải làm gì?**

→ Dùng nút "Trả lời công khai" để nêu thông tin đúng ngay dưới đánh giá đó. Nếu nội dung thuộc một trong các lý do từ chối có sẵn (spam, xúc phạm, lạc đề…) thì từ chối kèm lý do. Không được gỡ chỉ vì nó cho điểm thấp.

**Câu 6: Khách mua đi mua lại một sản phẩm thì đánh giá được mấy lần?**

→ Mỗi đơn hàng một lần. Mua 3 lần thì viết được 3 đánh giá, vì đó là 3 lần trải nghiệm thật.

**Câu 7: Đánh giá đang chờ duyệt có làm tụt điểm sao của tôi không?**

→ Không. Điểm trung bình chỉ tính đánh giá đã duyệt.

**Câu 8: Website tôi có nhiều cửa hàng, cài đặt có dùng chung không?**

→ Mỗi cửa hàng chỉnh riêng được. Cửa hàng chưa chỉnh gì sẽ dùng cài đặt mặc định chung.

**Câu 9: Khi cập nhật plugin lên phiên bản mới, tôi có mất cài đặt và dữ liệu không?**

→ Không. Các lựa chọn của bạn và toàn bộ đánh giá đều được giữ lại; ảnh khách gửi cũng lưu ngoài thư mục plugin nên không bị ghi đè.

**Câu 10: Khách yêu cầu xoá đánh giá vì lý do riêng tư thì sao?**

→ Khách tự bấm "Gỡ đánh giá của tôi" ngay trên trang sản phẩm; nội dung và ảnh bị xoá thật. Nếu cần xoá sạch cả dấu vết, nhờ quản trị hệ thống dùng nút xoá.

---

<sub>📅 **Cập nhật lần cuối:** 2026-09-13 · ✍️ **Tác giả (Author):** GP247</sub>
