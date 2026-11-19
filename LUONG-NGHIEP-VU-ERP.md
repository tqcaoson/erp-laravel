# Luồng nghiệp vụ hệ thống ERP

Tài liệu này mô tả các luồng nghiệp vụ của hệ thống ERP đang vận hành cho Công ty TNHH XT Mechanical: mỗi tính năng làm gì, các bước thực hiện theo thứ tự, vai trò nào thực hiện bước nào, các quy tắc nghiệp vụ quan trọng, và tính năng này nối với tính năng khác ra sao. Tài liệu chỉ mô tả nghiệp vụ, không mô tả kỹ thuật.

**Cách đọc tài liệu**

- Mỗi mục tính năng gồm: mô tả ngắn, các bước thực hiện (đánh số theo thứ tự), vai trò thực hiện, quy tắc nghiệp vụ, và mục "Nối với" chỉ ra tính năng liên quan.
- Tên các vai trò trong tài liệu: **Quản trị hệ thống**, **Giám đốc**, **Kế toán**, **Thủ kho**, **Nhân viên bán hàng**, **Nhân viên mua hàng**, **Nhân viên sản xuất**, **Nhân sự**, **Nhân viên**. Xem mục 0.2 để biết mỗi vai trò được làm những gì.
- Các trạng thái chứng từ dùng chung trong toàn hệ thống: **Nháp** (mới lập, chưa có hiệu lực), **Chờ duyệt** (đang đợi người có quyền duyệt), **Đã duyệt / Đã xác nhận** (đã có hiệu lực), **Hoàn tất** (đã xử lý xong toàn bộ), **Đã hủy** (không còn hiệu lực). Chứng từ đã hủy không xóa khỏi hệ thống mà giữ lại để đối chiếu.

---

## 0. Hệ thống chung

### 0.1. Công ty và chuyển đổi công ty

Hệ thống làm việc theo từng công ty. Mỗi người dùng có thể thuộc một hoặc nhiều công ty; dữ liệu chứng từ, danh mục và số liệu của công ty nào chỉ thuộc công ty đó. Người dùng chuyển đổi công ty đang làm việc ở thanh phía trên màn hình, và toàn bộ số liệu hiển thị sau đó đều theo công ty vừa chọn.

**Quy tắc**

- Mọi chứng từ đều gắn với một công ty cụ thể và không dùng chung giữa các công ty.
- Một số danh mục dùng chung cho mọi công ty: đơn vị tính (cái, thùng, kg...), tiền tệ (đồng Việt Nam, đô la Mỹ).
- Bản dùng thử hiện có một công ty: Công ty TNHH XT Mechanical.

### 0.2. Người dùng, vai trò và quyền

Mỗi người dùng đăng nhập bằng tài khoản riêng và được gắn một vai trò. Vai trò quyết định người đó được xem và được làm những gì: menu chức năng chỉ hiện những mục mà vai trò của người đăng nhập được phép dùng, và các thao tác vượt quyền đều bị hệ thống chặn.

| Vai trò | Được làm |
|---|---|
| Quản trị hệ thống | Mọi thao tác trong toàn hệ thống |
| Giám đốc | Xem mọi nội dung; duyệt đơn bán vượt hạn mức, duyệt đơn mua, duyệt yêu cầu mua, xác nhận phiếu nhận hàng, duyệt phiếu kho, duyệt nghỉ phép, duyệt bảng lương; xem mọi báo cáo |
| Kế toán | Toàn bộ nghiệp vụ kế toán; quản lý nhà cung cấp; đánh dấu đã trả lương sau khi bảng lương được duyệt; xem báo cáo; xem đơn bán, khách hàng, đơn mua và bảng lương |
| Thủ kho | Toàn bộ nghiệp vụ kho; xem đơn mua và nhà cung cấp, lập phiếu nhận hàng; xem và xác nhận phiếu giao hàng; xem bảng tổng quan |
| Nhân viên bán hàng | Toàn bộ nghiệp vụ bán hàng và chăm sóc khách hàng; xem sản phẩm và tồn kho; xem báo cáo |
| Nhân viên mua hàng | Lập yêu cầu mua, đơn mua và yêu cầu báo giá; quản lý nhà cung cấp; lập phiếu nhận hàng; xem sản phẩm và tồn kho |
| Nhân viên sản xuất | Tạo, xác nhận, bắt đầu và hoàn thành lệnh sản xuất; thêm/sửa/xóa và cập nhật công đoạn, ghi nhận tiến độ sản xuất; xem định mức, quy trình, trung tâm sản xuất, sản phẩm và tồn kho |
| Nhân sự | Toàn bộ nghiệp vụ nhân sự; tính lương và khóa bảng lương; xem bảng tổng quan và báo cáo |
| Nhân viên | Tự chấm công vào/ra trong ngày, tự đăng ký nghỉ phép của mình và xem phiếu lương của mình; chỉ thấy dữ liệu của chính mình |

**Tài khoản dùng thử** (mật khẩu của tất cả các tài khoản đều là `password`):

| Người dùng | Tài khoản | Vai trò |
|---|---|---|
| Quản trị hệ thống | admin@erp.local | Quản trị hệ thống |
| Nguyễn Văn Hùng | giamdoc@erp.local | Giám đốc |
| Trần Thị Lan | ketoan@erp.local | Kế toán |
| Phạm Văn Kho | kho@erp.local | Thủ kho |
| Lê Thị Hoa | banhang@erp.local | Nhân viên bán hàng |
| Hoàng Văn Phúc | muahang@erp.local | Nhân viên mua hàng |
| Vũ Thị Hằng | sanxuat@erp.local | Nhân viên sản xuất |
| Đỗ Thị Mai | nhansu@erp.local | Nhân sự |
| Trần Quốc Bảo | nhanvien@erp.local | Nhân viên |

**Lưu ý:** vai trò và quyền đã được thiết lập sẵn trong hệ thống. Quản trị hệ thống tự tạo thêm người dùng, gán vai trò và vô hiệu tài khoản trên màn hình quản lý người dùng (xem mục 0.5); việc sửa nội dung quyền của từng vai trò vẫn do quản trị hệ thống thực hiện trực tiếp trên dữ liệu.

### 0.3. Mã chứng từ tự động

Mọi chứng từ đều được hệ thống tự sinh mã theo dạng **tiền tố – năm – số thứ tự**, ví dụ đơn bán `SO-2026-0001`, đơn mua `PO-2026-0001`, phiếu nhận hàng `GR-2026-0001`. Số thứ tự chạy riêng theo từng loại chứng từ, từng công ty và từng năm, đảm bảo không trùng và dễ tra cứu.

### 0.4. Phê duyệt và nhật ký

**Phê duyệt.** Một số chứng từ bắt buộc qua bước duyệt trước khi có hiệu lực (xem chi tiết ở từng mục: yêu cầu mua trên 50 triệu đồng, đơn mua hàng, đơn bán vượt hạn mức công nợ, phiếu nhận hàng, phiếu kho nhập/xuất/chuyển, đơn nghỉ phép, duyệt bảng lương sau khi bảng lương đã khóa). Mỗi lần duyệt hoặc từ chối, hệ thống ghi lại người duyệt, quyết định, ghi chú và thời điểm để đối chiếu sau này.

**Nhật ký thay đổi.** Mọi thao tác tạo mới, sửa đổi và xóa dữ liệu trong hệ thống đều được ghi lại tự động vào nhật ký, gồm người thực hiện, thời điểm và nội dung thay đổi. Nhật ký này phục vụ kiểm tra và đối soát; bản hiện tại chưa có màn hình xem trực tiếp nhật ký.

### 0.5. Quản lý người dùng và tài khoản đăng nhập

Quản trị hệ thống quản lý tài khoản đăng nhập của mọi người dùng: tạo tài khoản mới, gán vai trò, đặt lại mật khẩu, vô hiệu hoặc kích hoạt lại tài khoản, và xóa tài khoản không dùng nữa. Mỗi tài khoản gắn với một vai trò (xem mục 0.2) và có thể gắn với một hồ sơ nhân viên (xem mục 6.2) để người đó dùng được các tính năng tự phục vụ của nhân viên.

**Các bước**

1. Quản trị hệ thống tạo tài khoản mới: nhập tên, địa chỉ thư điện tử, đặt mật khẩu ban đầu, chọn vai trò và công ty người đó thuộc về.
2. Khi tạo tài khoản cho một nhân viên, quản trị hệ thống có thể đánh dấu ô "Tạo luôn hồ sơ nhân viên" ngay trên cùng màn hình tạo tài khoản: chọn phòng ban (bắt buộc), chức danh, số điện thoại, ngày vào làm và lương cơ bản nếu đã biết. Hệ thống tự tạo hồ sơ nhân viên gắn với tài khoản vừa tạo và tự sinh mã nhân viên. Tài khoản đăng nhập được ngay và dùng được các tính năng tự phục vụ của nhân viên (chấm công, xin nghỉ, xem phiếu lương) mà không cần sang màn hình hồ sơ nhân viên để gắn thêm. Khi chọn vai trò Nhân viên thì ô này được đánh dấu sẵn, vẫn bỏ đánh dấu được nếu chưa muốn tạo hồ sơ.
3. Khi cần, quản trị hệ thống đặt lại mật khẩu cho tài khoản hoặc đổi vai trò gắn với tài khoản.
4. Khi nhân viên nghỉ việc hoặc tài khoản không dùng nữa, quản trị hệ thống vô hiệu tài khoản đó. Tài khoản bị vô hiệu không đăng nhập được nữa; nếu người đó đang đăng nhập thì sẽ bị đăng xuất ở thao tác tiếp theo.
5. Tài khoản bị vô hiệu có thể kích hoạt lại khi cần dùng tiếp.
6. Tài khoản không dùng nữa có thể xóa; quản trị hệ thống không tự xóa hay tự vô hiệu tài khoản của chính mình.

**Vai trò:** Quản trị hệ thống.

**Quy tắc**

- Mật khẩu ban đầu do quản trị hệ thống đặt khi tạo tài khoản.
- Tài khoản đăng nhập nên gắn với hồ sơ nhân viên tương ứng để nhân viên dùng được chấm công, nghỉ phép và phiếu lương của mình (xem mục 6.3, 6.4, 6.5).
- Khi tạo kèm hồ sơ nhân viên, lương cơ bản được để trống thì bằng 0 cho tới khi nhân sự cập nhật — bảng lương của nhân viên đó sẽ bằng 0 trong thời gian chưa cập nhật lương.
- Xóa tài khoản không xóa hồ sơ nhân viên gắn kèm; hồ sơ trở thành chưa gắn tài khoản và có thể gắn lại sau.

**Nối với:** vai trò và quyền (mục 0.2); hồ sơ nhân viên (mục 6.2).

### 0.6. Dữ liệu dùng chung

- **Đơn vị tính:** danh mục các đơn vị như cái, thùng, kg, hộp. Sản phẩm có đơn vị tính chính và có thể có thêm đơn vị quy đổi (ví dụ 1 thùng = 24 chai).
- **Tiền tệ:** đồng Việt Nam (đơn vị tiền tệ chính) và đô la Mỹ kèm tỷ giá quy đổi.

### 0.7. Nhập số tiền và số lượng trên các form

Các ô nhập tiền và số lượng trên mọi màn hình lập chứng từ đều chấp nhận gõ số theo nhiều cách: gõ `20000`, `20,000` hay `20.000` đều được hiểu là hai mươi nghìn. Sau khi nhập xong, hệ thống hiển thị lại số đã hiểu để người nhập kiểm tra.

**Quy tắc**

- Trong ô nhập tiền (đồng Việt Nam), dấu chấm luôn là dấu phân cách nghìn — không nhập được số lẻ đồng vì đồng Việt Nam không có xu lẻ.
- Ô nhập số lượng giữ được số thập phân, ví dụ nhập 0,5 kg vẫn được.
- Khi một dòng hàng nhập sai (ví dụ số lượng bằng 0), lỗi hiển thị ngay dưới ô nhập của dòng đó để sửa tại chỗ.

---

## 1. Kế toán

### 1.1. Hệ thống tài khoản

Hệ thống tài khoản là danh mục tài khoản kế toán dùng chung cho mọi bút toán trong công ty, xây dựng theo Thông tư 200 (bản rút gọn, khoảng 35 tài khoản) với cấu trúc tài khoản cha – con (ví dụ tài khoản 131 phải thu của khách hàng, 156 hàng hóa, 511 doanh thu bán hàng, 632 giá vốn hàng bán).

**Các bước**

1. Kế toán mở danh mục hệ thống tài khoản để xem toàn bộ tài khoản theo từng nhóm (tài sản, nợ phải trả, vốn chủ sở hữu, doanh thu, chi phí).
2. Khi cần thêm tài khoản mới, kế toán tạo tài khoản con dưới một tài khoản cha đã có, đặt mã và tên tài khoản.

**Vai trò:** Kế toán (xem và quản lý); Giám đốc (xem).

**Quy tắc**

- Tài khoản cha không được dùng trực tiếp trên dòng bút toán, chỉ tài khoản chi tiết mới được hạch toán.
- Mọi bút toán tự động của hệ thống (ghi nhận doanh thu, giá vốn, lương...) đều hạch toán vào các tài khoản trong danh mục này, nên danh mục phải đầy đủ trước khi vận hành.

### 1.2. Kỳ kế toán

Kỳ kế toán được tính theo tháng (ví dụ kỳ 2026-01 là tháng 1 năm 2026). Hệ thống tự tạo kỳ của tháng đó ở trạng thái mở khi có chứng từ đầu tiên phát sinh trong tháng.

**Các bước**

1. Kế toán xem danh sách các kỳ kế toán và trạng thái mở/khóa của từng kỳ.
2. Cuối tháng, sau khi đã kiểm tra xong số liệu, kế toán khóa kỳ để chốt sổ.
3. Nếu phát hiện cần điều chỉnh sau khi đã khóa, kế toán mở lại kỳ, điều chỉnh rồi khóa lại.

**Vai trò:** Kế toán.

**Quy tắc**

- Chứng từ thuộc kỳ đã khóa không được ghi sổ; hệ thống từ chối và báo rõ lý do.
- Bút toán nháp vẫn lập được trong kỳ đã khóa, nhưng chỉ ghi sổ được sau khi kỳ được mở lại.

**Nối với:** mọi bút toán, hóa đơn và phiếu thu/chi đều gắn với kỳ kế toán theo ngày chứng từ.

### 1.3. Bút toán

Bút toán là chứng từ ghi nhận các nghiệp vụ kế toán theo nguyên tắc nợ – có. Bút toán có thể do kế toán nhập tay hoặc do hệ thống tự sinh từ các nghiệp vụ khác (bán hàng, mua hàng, giao hàng, lương).

**Các bước**

1. Kế toán lập bút toán nháp: chọn ngày hạch toán, ghi diễn giải, nhập các dòng nợ và có theo từng tài khoản (có thể gắn kèm khách hàng hoặc nhà cung cấp cho dòng công nợ).
2. Kế toán kiểm tra và ghi sổ bút toán. Hệ thống kiểm tra tổng nợ phải bằng tổng có rồi mới cho ghi sổ.
3. Nếu bút toán đã ghi sổ bị sai, kế toán thực hiện đảo bút toán: hệ thống sinh một bút toán mới đảo ngược nợ – có và ghi sổ ngay, bút toán gốc chuyển sang trạng thái đã đảo nhưng vẫn giữ nguyên để đối chiếu. Sau khi đảo, hệ thống quay về bút toán gốc và hiện rõ trạng thái đã đảo cùng bút toán đảo tương ứng.

**Vai trò:** Kế toán (lập, ghi sổ, đảo bút toán); Giám đốc (xem).

**Quy tắc**

- Bút toán nháp được phép lưu khi chưa cân bằng để kế toán làm dở dang, nhưng khi ghi sổ bắt buộc tổng nợ phải bằng tổng có (sai lệch cho phép dưới 0,01 đồng).
- Bút toán đã ghi sổ không được sửa hay xóa trực tiếp; muốn sửa sai sót phải đảo bút toán rồi lập bút toán mới.
- Bút toán đảo không đảo tiếp được.
- Bút toán thuộc kỳ đã khóa không ghi sổ được.

**Nối với:** hóa đơn (mục 1.4), thu chi (mục 1.5), giao hàng (mục 4.6, sinh bút toán giá vốn), nhận hàng (mục 2.5, sinh bút toán nhập hàng), bảng lương (mục 6.5, sinh bút toán lương).

### 1.4. Hóa đơn

Hóa đơn gồm hóa đơn bán hàng (xuất cho khách hàng) và hóa đơn mua hàng (nhận từ nhà cung cấp). Hóa đơn bán có thể lập từ đơn bán đã giao hàng (xem phần lập hóa đơn từ đơn bán dưới đây) hoặc lập độc lập: chọn loại hóa đơn, nhập mã khách hàng hoặc nhà cung cấp, ngày hóa đơn, hạn thanh toán và các dòng hàng hóa/dịch vụ kèm số lượng, đơn giá và thuế suất. Hệ thống tự tính tiền hàng, tiền thuế và tổng tiền.

**Các bước**

1. Kế toán lập hóa đơn ở trạng thái nháp.
2. Kế toán ghi sổ hóa đơn. Hệ thống tự sinh bút toán:
   - Hóa đơn bán: ghi nhận phải thu của khách hàng, doanh thu bán hàng và thuế giá trị gia tăng đầu ra.
   - Hóa đơn mua: ghi nhận giá trị hàng hóa, thuế giá trị gia tăng được khấu trừ và phải trả nhà cung cấp.
3. Khi khách hàng thanh toán hoặc công ty thanh toán cho nhà cung cấp, kế toán ghi nhận thanh toán trên hóa đơn (xem mục 1.5). Thanh toán có thể chia nhiều lần; khi đủ tiền, hóa đơn chuyển sang trạng thái đã thanh toán xong.
4. Hóa đơn nháp lập sai thì hủy được. Hóa đơn đã ghi sổ thì không hủy trực tiếp.

**Lập hóa đơn bán từ đơn bán**

1. Mở đơn bán đã giao hàng (giao một phần hoặc giao đủ) và bấm lập hóa đơn. Hệ thống tạo hóa đơn nháp từ phần hàng đã giao mà chưa lập hóa đơn, giữ nguyên đơn giá, thuế suất và chiết khấu từng dòng trên đơn bán; chiết khấu chung của đơn bán được trừ đều theo tỷ lệ phần đã giao.
2. Kế toán kiểm tra hóa đơn nháp rồi ghi sổ như hóa đơn thường. Doanh thu chỉ ghi nhận khi hóa đơn được ghi sổ — hóa đơn nháp chưa làm thay đổi doanh thu trên bảng tổng quan hay báo cáo.
3. Nếu đơn còn phần đã giao chưa lập hóa đơn thì lập tiếp hóa đơn cho phần đó ở lần sau; hệ thống không lập trùng phần đã lập hóa đơn rồi.

**Lập hóa đơn mua từ phiếu nhận hàng**

1. Mở phiếu nhận hàng đã xác nhận và bấm lập hóa đơn mua. Hệ thống tạo hóa đơn mua nháp từ số hàng đã nhận mà chưa lập hóa đơn, lấy giá nhập trên phiếu nhận làm đơn giá.
2. Kế toán kiểm tra và bổ sung thuế trên hóa đơn nháp nếu cần: thuế suất từng dòng mặc định bằng 0 vì đơn mua ghi thuế ở cấp đơn, không ghi theo dòng. Sau đó ghi sổ hóa đơn như hóa đơn thường. Khi ghi sổ, hệ thống kết chuyển giá trị hàng từ tài khoản hàng về chưa có hóa đơn (335) sang phải trả nhà cung cấp (331) và ghi nhận thuế giá trị gia tăng được khấu trừ — không ghi trùng giá trị hàng hóa đã ghi nhận khi nhận hàng.
3. Nếu phiếu nhận còn phần hàng chưa lập hóa đơn thì lập tiếp hóa đơn cho phần đó ở lần sau; hệ thống không lập trùng phần đã lập hóa đơn rồi.
4. Hóa đơn mua nhập tay như trước đây vẫn dùng được bình thường.

**Vai trò:** Kế toán (lập, ghi sổ, ghi nhận thanh toán); Giám đốc (xem).

**Quy tắc**

- Chỉ hóa đơn nháp mới ghi sổ được; hóa đơn đã ghi sổ không sửa, không hủy.
- Số tiền thanh toán mỗi lần không được vượt số tiền còn phải thu/trả của hóa đơn.
- Hóa đơn bán lập từ đơn bán chỉ lấy phần hàng đã giao và chưa lập hóa đơn; đơn chưa giao hàng thì chưa lập được hóa đơn, phần đã lập hóa đơn rồi thì không lập lại được.
- Hóa đơn mua lập từ phiếu nhận chỉ lấy phần hàng đã nhận và chưa lập hóa đơn; phiếu nhận chưa xác nhận thì chưa lập được hóa đơn.

**Lưu ý quan trọng:** khi nhận hàng (mục 2.5), hệ thống ghi nhận giá trị hàng nhập kho vào tài khoản hàng về chưa có hóa đơn (335), chưa ghi vào phải trả nhà cung cấp (331). Vì vậy nên lập hóa đơn mua từ phiếu nhận để hệ thống tự kết chuyển 335 sang 331 khi ghi sổ. Hóa đơn mua nhập tay cho hàng đã nhận rồi thì kế toán vẫn cần đối chiếu tay để không ghi nhận trùng giá trị hàng hóa — bản hiện tại chưa tự động đối chiếu ba chiều giữa đơn mua, phiếu nhận hàng và hóa đơn mua nhập tay.

**Nối với:** đơn bán (mục 4.5) — đơn bán đã giao hàng đầy đủ sẽ chuyển sang trạng thái đã lập hóa đơn khi hóa đơn bán của đơn đó được ghi sổ; phiếu nhận hàng (mục 2.5) — phiếu nhận đã xác nhận lập được hóa đơn mua; thu chi (mục 1.5); báo cáo công nợ tuổi nợ (mục 7.3) và báo cáo công nợ phải trả (mục 7.4).

### 1.5. Thu chi và thanh toán

Phiếu thu/chi ghi nhận các khoản tiền thu vào và chi ra của công ty, gồm hai loại: thanh toán gắn với một hóa đơn cụ thể, và thu/chi độc lập không gắn hóa đơn.

**Thanh toán hóa đơn — các bước**

1. Kế toán mở hóa đơn đã ghi sổ và ghi nhận thanh toán: nhập số tiền, ngày thanh toán, chọn tiền mặt hoặc tài khoản ngân hàng, ghi chú nếu cần.
2. Hệ thống tự sinh phiếu thu (với hóa đơn bán) hoặc phiếu chi (với hóa đơn mua) ở trạng thái đã ghi sổ, đồng thời sinh bút toán tiền: thu tiền bán hàng làm giảm phải thu khách hàng; trả tiền mua hàng làm giảm phải trả nhà cung cấp.
3. Khi tổng số tiền đã thanh toán bằng tổng tiền hóa đơn, hóa đơn chuyển sang trạng thái đã thanh toán xong.

**Thu/chi độc lập — các bước**

1. Kế toán lập phiếu thu hoặc phiếu chi ở trạng thái nháp: chọn đối tác (khách hàng/nhà cung cấp), số tiền, ngày, tiền mặt hay tài khoản ngân hàng.
2. Kế toán ghi sổ phiếu. Hệ thống sinh bút toán: thu độc lập ghi nhận thu nhập khác, chi độc lập ghi nhận chi phí khác.

**Vai trò:** Kế toán.

**Quy tắc**

- Chỉ thanh toán được hóa đơn đã ghi sổ.
- Số tiền thanh toán không vượt số tiền còn lại của hóa đơn.
- Phiếu thu/chi đã ghi sổ không hủy được.
- Thu/chi bằng tiền mặt hạch toán qua tài khoản tiền mặt; thu/chi qua ngân hàng hạch toán qua tài khoản tiền gửi ngân hàng.

### 1.6. Tài khoản ngân hàng

Tài khoản ngân hàng là các tài khoản tiền gửi của công ty tại ngân hàng, dùng để ghi nhận thu/chi qua ngân hàng thay vì tiền mặt. Khi lập phiếu thu/chi hoặc thanh toán hóa đơn, kế toán chọn tài khoản ngân hàng tương ứng để hệ thống hạch toán đúng.

**Lưu ý:** bản hiện tại chưa có màn hình quản lý tài khoản ngân hàng và chưa có tính năng đối chiếu sao kê ngân hàng; danh mục tài khoản ngân hàng do quản trị thiết lập sẵn trong dữ liệu. Phiếu thu/chi mặc định ghi nhận bằng tiền mặt khi không chọn tài khoản ngân hàng.

### 1.7. Công nợ phải thu và phải trả

Công nợ phải thu là số tiền khách hàng còn nợ công ty (từ các hóa đơn bán đã ghi sổ chưa thanh toán hết, theo dõi trên tài khoản 131); công nợ phải trả là số tiền công ty còn nợ nhà cung cấp (từ các hóa đơn mua đã ghi sổ chưa thanh toán hết, theo dõi trên tài khoản 331). Hàng đã nhận về kho nhưng chưa có hóa đơn mua thì nằm ở tài khoản hàng về chưa có hóa đơn (335) và chưa tính vào công nợ phải trả; khi hóa đơn mua từ phiếu nhận được ghi sổ thì số tiền chuyển từ 335 sang 331.

Kế toán theo dõi công nợ theo từng khách hàng/nhà cung cấp và theo tuổi nợ: công nợ phải thu xem ở báo cáo tuổi nợ (mục 7.3), công nợ phải trả xem ở báo cáo công nợ phải trả (mục 7.4), trong đó có cả mục hàng đã nhận chưa có hóa đơn (335).

**Nối với:** hạn mức công nợ của khách hàng (mục 4.1) — hệ thống dùng công nợ hiện tại để kiểm tra khi xác nhận đơn bán mới; phiếu nhận hàng (mục 2.5) và hóa đơn mua (mục 1.4).

---

## 2. Mua hàng

### 2.1. Nhà cung cấp

Nhà cung cấp là danh mục các đơn vị cung cấp hàng hóa cho công ty, gồm mã, tên, mã số thuế, điện thoại, email, địa chỉ, số ngày được nợ khi thanh toán (điều khoản thanh toán) và trạng thái đang hợp tác hay đã ngừng.

**Các bước**

1. Nhân viên mua hàng hoặc Kế toán tạo mới nhà cung cấp với đầy đủ thông tin liên hệ và điều khoản thanh toán.
2. Khi thông tin thay đổi, nhân viên mua hàng hoặc Kế toán sửa lại hồ sơ nhà cung cấp.
3. Nhà cung cấp ngừng hợp tác được đánh dấu ngừng hoạt động thay vì xóa, để lịch sử giao dịch cũ vẫn đối chiếu được. Nhà cung cấp chưa phát sinh giao dịch thì xóa được.

**Vai trò:** Nhân viên mua hàng và Kế toán (quản lý nhà cung cấp); Quản trị hệ thống (quản lý); Giám đốc và Thủ kho (xem).

**Nối với:** yêu cầu báo giá, đơn mua hàng, phiếu nhận hàng, hóa đơn mua (đều gắn với một nhà cung cấp).

### 2.2. Yêu cầu mua hàng

Yêu cầu mua hàng là đề nghị mua sắm từ các bộ phận trong công ty (mua nguyên vật liệu, hàng hóa, vật tư...). Ngoài yêu cầu do người dùng lập, hệ thống còn tự tạo yêu cầu mua ở trạng thái nháp để gợi ý khi tồn kho xuống dưới mức tối thiểu, khi đơn bán thiếu hàng, hoặc khi lệnh sản xuất thiếu nguyên liệu.

**Các bước**

1. Bộ phận có nhu cầu gửi đề nghị mua; Nhân viên mua hàng lập yêu cầu mua trên hệ thống: chọn sản phẩm, số lượng, kho cần hàng, đơn giá ước tính và ghi chú.
2. Hệ thống tự trình duyệt theo tổng giá trị yêu cầu:
   - Tổng giá trị từ 50 triệu đồng trở xuống: yêu cầu được duyệt tự động.
   - Tổng giá trị trên 50 triệu đồng: yêu cầu chuyển sang trạng thái chờ duyệt.
3. Với yêu cầu chờ duyệt, người có quyền duyệt sẽ duyệt hoặc từ chối (kèm ghi chú lý do). Quyết định duyệt/từ chối được ghi lại.
4. Yêu cầu đã duyệt được chuyển thành đơn mua hàng (mục 2.4); khi đó yêu cầu chuyển sang trạng thái đã chuyển thành đơn mua.
5. Yêu cầu chưa chuyển thành đơn mua thì hủy được.

**Vai trò:** Nhân viên mua hàng (lập yêu cầu mua); Giám đốc (duyệt yêu cầu trên 50 triệu đồng).

**Quy tắc**

- Ngưỡng phải duyệt là tổng giá trị trên 50 triệu đồng.
- Yêu cầu đã chuyển thành đơn mua thì không hủy được.
- Yêu cầu do hệ thống tự gợi ý luôn ở trạng thái nháp để người phụ trách xem xét trước khi trình duyệt.

**Nối với:** đơn mua hàng (mục 2.4); tồn kho (mục 3.6) và đơn bán thiếu hàng (mục 4.5) là nguồn phát sinh yêu cầu gợi ý.

### 2.3. Yêu cầu báo giá

Yêu cầu báo giá là văn bản gửi nhà cung cấp để hỏi giá trước khi đặt mua, dùng khi cần so sánh giá giữa các nhà cung cấp hoặc mua mặt hàng chưa có giá thỏa thuận.

**Các bước**

1. Nhân viên mua hàng lập yêu cầu báo giá: chọn nhà cung cấp, nhập các mặt hàng cần báo giá kèm số lượng, đặt hạn trả lời và ghi chú.
2. Gửi yêu cầu cho nhà cung cấp (trạng thái chuyển sang đã gửi).
3. Khi nhà cung cấp trả lời, cập nhật trạng thái thành đã nhận báo giá và nhập giá từng mặt hàng.
4. So sánh và quyết định: chấp nhận báo giá của nhà cung cấp được chọn, hoặc từ chối.
5. Báo giá đã chấp nhận có thể tạo đơn mua hàng ngay từ báo giá: hệ thống tự điền sẵn nhà cung cấp, các mặt hàng và giá đã báo vào đơn mua mới để kiểm tra rồi trình duyệt.
6. Yêu cầu báo giá còn ở trạng thái nháp thì xóa được.

**Vai trò:** Nhân viên mua hàng (quản lý yêu cầu báo giá); Giám đốc (xem).

**Quy tắc**

- Trạng thái đi theo thứ tự: nháp → đã gửi → đã nhận báo giá → chấp nhận/từ chối; không nhảy cóc trạng thái.
- Báo giá đã chấp nhận hoặc đã từ chối là quyết định cuối cùng, không đổi lại được.

**Nối với:** nhà cung cấp (mục 2.1); đơn mua hàng (mục 2.4) được lập sau khi đã chọn báo giá.

### 2.4. Đơn mua hàng

Đơn mua hàng là chứng từ đặt mua chính thức gửi nhà cung cấp, có thể lập từ một yêu cầu mua đã duyệt, từ một báo giá đã chấp nhận hoặc nhập trực tiếp.

**Các bước**

1. Nhân viên mua hàng lập đơn mua ở trạng thái nháp: chọn nhà cung cấp (và yêu cầu mua nguồn hoặc báo giá nguồn nếu có), ngày đặt hàng, ngày dự kiến nhận hàng, các dòng sản phẩm kèm số lượng và đơn giá. Hệ thống tự tính tiền hàng và tổng tiền.
2. Trình duyệt đơn mua (chuyển sang chờ duyệt), hoặc duyệt trực tiếp nếu có quyền.
3. Người có quyền duyệt sẽ duyệt đơn. Quyết định duyệt được ghi lại kèm ghi chú.
4. Nhà cung cấp giao hàng theo đơn; mỗi lần giao hàng được ghi nhận bằng phiếu nhận hàng (mục 2.5). Đơn mua tự cập nhật trạng thái: còn thiếu hàng thì ở trạng thái nhận một phần; nhận đủ hết thì chuyển sang đã nhận đủ. Danh sách đơn mua hiển thị tiến độ nhận của từng đơn (đã nhận bao nhiêu trên tổng số đã đặt).
5. Khi đã nhận đủ hàng, người có quyền duyệt đóng đơn mua lại.
6. Đơn mua chưa nhận hàng dòng nào thì hủy được; đã nhận hàng rồi thì không hủy được.

**Vai trò:** Nhân viên mua hàng (lập, trình duyệt, hủy đơn mua); Giám đốc (duyệt, đóng đơn); Kế toán và Thủ kho (xem).

**Quy tắc**

- Chỉ nhận hàng khi đơn mua đã được duyệt.
- Không nhận vượt số lượng đã đặt mua trên từng dòng.
- Đơn mua chỉ đóng được khi đã nhận đủ hàng.
- Danh sách đơn mua có ô tìm theo mã đơn để tra cứu nhanh một đơn cụ thể, ngoài bộ lọc theo nhà cung cấp và trạng thái.

**Nối với:** yêu cầu mua (mục 2.2); yêu cầu báo giá (mục 2.3) — báo giá đã chấp nhận lập được đơn mua; phiếu nhận hàng (mục 2.5); hóa đơn mua (mục 1.4); tồn kho (mục 3.3) tăng lên khi nhận hàng.

### 2.5. Nhận hàng

Phiếu nhận hàng ghi nhận hàng thực tế đã về kho theo đơn mua, hoặc nhập hàng không theo đơn mua (nhập trực tiếp từ nhà cung cấp).

**Các bước**

1. Thủ kho lập phiếu nhận hàng: chọn đơn mua đã duyệt (hệ thống tự điền các mặt hàng và số lượng còn thiếu) hoặc chọn nhà cung cấp để nhập trực tiếp, chọn kho nhập, ngày nhận và số lượng thực nhận từng mặt hàng kèm giá nhập.
2. Giám đốc xác nhận phiếu nhận. Hệ thống đối chiếu số lượng nhận với số lượng còn thiếu của đơn mua: nhận vượt số thiếu sẽ bị từ chối. Bảng tổng quan có cảnh báo phiếu nhận đang chờ xác nhận để Giám đốc không bỏ sót.
3. Khi phiếu được xác nhận, hệ thống tự động:
   - Tăng tồn kho tại kho nhận hàng và tính lại giá vốn bình quân của sản phẩm theo giá nhập thực tế.
   - Ghi nhận giá trị hàng nhập kho vào tài khoản hàng về chưa có hóa đơn (335). Khi hóa đơn mua từ phiếu nhận này được ghi sổ, số tiền mới chuyển sang phải trả nhà cung cấp (331) — xem mục 1.4 và 1.7.
   - Cập nhật số lượng đã nhận và trạng thái của đơn mua.
4. Trên trang chi tiết phiếu nhận, mỗi dòng hàng hiển thị rõ số lượng đã đặt trên đơn mua, số lượng nhận trong phiếu này và số lượng còn thiếu sau phiếu này để đối chiếu.
5. Phiếu nhận hàng còn ở trạng thái nháp thì hủy được. Phiếu nhận đã xác nhận thì lập được hóa đơn mua từ phiếu nhận (mục 1.4).

**Vai trò:** Thủ kho (lập, hủy phiếu nhận); Giám đốc (xác nhận phiếu nhận).

**Quy tắc**

- Chỉ xác nhận được phiếu ở trạng thái nháp.
- Tồn kho chỉ tăng khi phiếu nhận đã được xác nhận; phiếu còn ở trạng thái nháp thì chưa làm thay đổi tồn kho.
- Số lượng nhận không vượt số lượng đơn mua còn thiếu; cho phép nhận từng phần qua nhiều phiếu.
- Giá nhập thực tế trên phiếu nhận là căn cứ tính lại giá vốn bình quân.

**Lưu ý:** xem lưu ý đối chiếu hóa đơn mua ở mục 1.4 — hóa đơn mua lập từ phiếu nhận được hệ thống tự kết chuyển 335 sang 331 khi ghi sổ; hóa đơn mua nhập tay cho hàng đã nhận rồi thì kế toán vẫn cần đối chiếu tay.

**Nối với:** đơn mua (mục 2.4); tồn kho và giá vốn (mục 3.3, 3.6); sổ kế toán (mục 1.3); hóa đơn mua (mục 1.4); báo cáo công nợ phải trả (mục 7.4).

---

## 3. Kho

### 3.1. Sản phẩm

Sản phẩm là danh mục hàng hóa mà công ty mua, bán, tồn kho và sản xuất. Mỗi sản phẩm có mã riêng (SKU), tên, đơn vị tính chính, giá bán, giá vốn bình quân hiện tại, mức tồn tối thiểu cần cảnh báo, phương pháp tính giá vốn, và có theo dõi theo lô hay không.

**Các bước**

1. Thủ kho tạo sản phẩm mới: nhập mã, tên, đơn vị tính, giá bán, mức tồn tối thiểu và chọn có theo dõi lô hay không.
2. Khi cần đổi giá bán, mức tồn tối thiểu hoặc thông tin khác, thủ kho sửa lại hồ sơ sản phẩm.
3. Sản phẩm ngừng kinh doanh được đánh dấu ngừng hoạt động thay vì xóa.

**Vai trò:** Thủ kho (quản lý sản phẩm); Nhân viên bán hàng và Giám đốc (xem).

**Quy tắc**

- Mã sản phẩm là duy nhất, dùng để tra cứu nhanh trong mọi chứng từ.
- Giá vốn bình quân của sản phẩm do hệ thống tự tính lại mỗi lần nhập hàng, không nhập tay.
- Sản phẩm có thể có thêm đơn vị quy đổi (ví dụ 1 thùng = 24 chai) để nhập/xuất theo đơn vị khác nhau.

**Nối với:** mọi chứng từ mua, bán, kho và sản xuất đều gắn với sản phẩm.

### 3.2. Kho và vị trí

Công ty có thể có nhiều kho (bản dùng thử có kho Hà Nội và kho Thành phố Hồ Chí Minh). Mỗi kho có mã, tên, địa chỉ và trạng thái hoạt động. Trong mỗi kho có các vị trí lưu hàng (khu, kệ, ô) để ghi nhận hàng đang để ở đâu.

**Các bước**

1. Thủ kho tạo kho mới với mã, tên và địa chỉ.
2. Thủ kho sửa thông tin kho hoặc đánh dấu ngừng hoạt động khi kho không dùng nữa.

**Vai trò:** Thủ kho.

**Quy tắc**

- Mọi phiếu nhập, xuất, chuyển kho đều phải chọn rõ kho (và vị trí nếu có).
- Vị trí trong kho đã được thiết lập sẵn trong dữ liệu dùng thử; bản hiện tại chưa có màn hình tự thêm vị trí mới.

**Nối với:** nhập/xuất/chuyển kho (mục 3.3), kiểm kê (mục 3.5), đơn bán (chọn kho giao hàng), đơn mua (chọn kho nhận hàng).

### 3.3. Nhập kho, xuất kho, chuyển kho

Phiếu kho là chứng từ ghi nhận hàng hóa đi vào, đi ra hoặc di chuyển giữa các kho, do thủ kho lập trực tiếp (khác với nhập/xuất tự động từ nhận hàng, giao hàng hay sản xuất). Phiếu kho phải qua bước duyệt trước khi làm thay đổi tồn kho.

**Các bước**

1. Thủ kho mở phiếu kho mới và chọn loại phiếu: nhập kho, xuất kho hoặc chuyển kho.
2. Chọn sản phẩm, kho (với chuyển kho thì chọn cả kho nguồn và kho đích), vị trí và lô nếu có, nhập số lượng; với phiếu nhập thì nhập thêm giá nhập.
3. Thủ kho gửi phiếu. Phiếu nằm trong danh sách phiếu kho chờ duyệt và chưa làm thay đổi tồn kho.
4. Giám đốc xem danh sách phiếu kho chờ duyệt rồi duyệt hoặc từ chối từng phiếu. Khi phiếu được duyệt, hệ thống ghi nhận thay đổi tồn ngay:
   - Nhập kho: tăng tồn và tính lại giá vốn bình quân theo giá nhập.
   - Xuất kho: giảm tồn theo giá vốn bình quân hiện tại.
   - Chuyển kho: giảm tồn ở kho nguồn và tăng tồn ở kho đích trong cùng một lần ghi nhận, giá vốn giữ nguyên.
5. Phiếu bị từ chối thì không làm thay đổi tồn kho.

**Vai trò:** Thủ kho (lập phiếu); Giám đốc (duyệt hoặc từ chối phiếu).

**Quy tắc**

- Phiếu kho chỉ làm thay đổi tồn khi đã được duyệt; phiếu đang chờ duyệt hoặc bị từ chối không làm thay đổi tồn kho.
- Xuất kho không được vượt số lượng tồn hiện có tại kho; hệ thống chặn và báo rõ số còn lại.
- Chuyển kho bắt buộc kho nguồn và kho đích khác nhau.
- Số lượng nhập/xuất phải lớn hơn 0.
- Mọi thay đổi tồn đều được ghi vào sổ kho (mục 3.4) và không sửa xóa được sau khi ghi.

**Nối với:** nhận hàng (mục 2.5) và giao hàng (mục 4.6) cũng làm thay đổi tồn kho theo cùng cơ chế; kiểm kê (mục 3.5) điều chỉnh tồn theo số thực tế.

### 3.4. Sổ kho

Sổ kho là lịch sử toàn bộ các lần nhập, xuất và chuyển kho của từng sản phẩm tại từng kho, ghi rõ thời gian, số lượng, giá vốn tại thời điểm đó và chứng từ nguồn phát sinh (phiếu nhận hàng, phiếu giao hàng, lệnh sản xuất, kiểm kê hay phiếu kho lập tay). Thủ kho dùng sổ kho để truy vết và đối soát tồn kho bất kỳ lúc nào.

**Quy tắc**

- Sổ kho chỉ ghi thêm, không sửa, không xóa — mọi điều chỉnh tồn đều phải qua một phiếu kho hoặc phiếu kiểm kê mới.
- Tồn kho hiện tại của sản phẩm bằng tổng các lần nhập trừ đi tổng các lần xuất trong sổ kho.

### 3.5. Kiểm kê kho

Kiểm kê là việc đếm hàng thực tế trong kho rồi đối chiếu với số liệu trên hệ thống, sau đó điều chỉnh tồn kho theo số thực tế.

**Các bước**

1. Thủ kho tạo phiếu kiểm kê: chọn kho cần kiểm, có thể kiểm toàn bộ sản phẩm có tồn tại kho hoặc chỉ kiểm một số sản phẩm chọn trước. Hệ thống tự chụp số lượng trên hệ thống tại thời điểm tạo phiếu.
2. Thủ kho (hoặc nhân viên kho) đếm hàng thực tế và nhập số đếm được vào từng dòng của phiếu. Hệ thống tự tính chênh lệch (thừa/thiếu) từng dòng.
3. Thủ kho hoàn tất phiếu kiểm kê. Hệ thống tự sinh phiếu điều chỉnh: dòng thừa hàng thì nhập kho tăng tồn theo giá vốn bình quân hiện tại, dòng thiếu hàng thì xuất kho giảm tồn.
4. Phiếu kiểm kê chưa hoàn tất thì hủy được.

**Vai trò:** Thủ kho.

**Quy tắc**

- Dòng nào chưa nhập số đếm thực tế sẽ được bỏ qua khi hoàn tất, không điều chỉnh.
- Nếu số thiếu vượt tồn hiện có thì phiếu không hoàn tất được — số liệu hệ thống đã sai lệch cần được xem xét lại trước.
- Sau khi hoàn tất, phiếu kiểm kê không sửa được nữa.

**Nối với:** tồn kho (mục 3.3) và sổ kho (mục 3.4).

### 3.6. Giá vốn và cảnh báo tồn kho

**Giá vốn bình quân.** Giá vốn của mỗi sản phẩm được tính theo phương pháp bình quân gia quyền trên tổng tồn mọi kho: mỗi lần nhập hàng, hệ thống tính lại giá vốn mới bằng (tồn cũ × giá vốn cũ + lượng nhập × giá nhập) chia cho (tồn cũ + lượng nhập). Khi xuất kho hay giao hàng, giá vốn lấy theo giá bình quân hiện tại. Bản hiện tại chưa áp dụng phương pháp nhập trước xuất trước cho việc tính giá vốn, dù trên hồ sơ sản phẩm có thể chọn phương pháp này.

**Cảnh báo tồn kho thấp.** Mỗi sản phẩm có mức tồn tối thiểu. Khi tồn khả dụng tại một kho xuống dưới mức tối thiểu, hệ thống tự tạo một yêu cầu mua gợi ý ở trạng thái nháp để người phụ trách xem xét và đặt mua bổ sung. Danh sách sản phẩm sắp hết hàng cũng hiển thị trong mục cảnh báo trên bảng tổng quan.

**Xuất hàng theo lô.** Với sản phẩm theo dõi lô, mỗi lần nhập hàng có thể gắn với một lô hàng. Khi xuất kho, hệ thống ưu tiên xuất phần hàng của lô đã nhập về trước. Bản hiện tại chưa theo dõi số serial từng chiếc và chưa cảnh báo theo hạn sử dụng của lô.

---

## 4. Bán hàng và chăm sóc khách hàng (CRM)

### 4.1. Khách hàng

Khách hàng là danh mục các đơn vị mua hàng của công ty, gồm mã, tên, mã số thuế, điện thoại, email, địa chỉ, số ngày được nợ khi thanh toán và hạn mức công nợ (số tiền tối đa khách được nợ).

**Các bước**

1. Nhân viên bán hàng tạo mới khách hàng với đầy đủ thông tin liên hệ và hạn mức công nợ.
2. Khi thông tin thay đổi (ví dụ nâng hạn mức công nợ), nhân viên bán hàng sửa lại hồ sơ khách hàng.
3. Khách hàng ngừng giao dịch được đánh dấu ngừng hoạt động thay vì xóa, để lịch sử bán hàng cũ vẫn đối chiếu được.

**Vai trò:** Nhân viên bán hàng (quản lý); Kế toán, Giám đốc (xem).

**Quy tắc**

- Hạn mức công nợ bằng 0 nghĩa là không giới hạn — khách được nợ không giới hạn.
- Công nợ hiện tại của khách được tính bằng tổng giá trị các đơn bán đã xác nhận, đang giao, đã giao và đã lập hóa đơn.

**Nối với:** báo giá, đơn bán, cơ hội bán hàng, hóa đơn bán và công nợ phải thu.

### 4.2. Đầu mối

Đầu mối là thông tin liên hệ của khách hàng tiềm năng chưa từng mua hàng (người liên hệ, tên công ty, điện thoại, email, nguồn biết đến công ty, ghi chú). Nhân viên bán hàng ghi nhận đầu mối khi tiếp nhận thông tin từ quảng cáo, hội chợ, giới thiệu...

**Các bước**

1. Nhân viên bán hàng tạo đầu mối mới với thông tin liên hệ có được.
2. Trong quá trình chăm sóc, cập nhật trạng thái đầu mối: mới → đã liên hệ → đủ tiêu chuẩn (có nhu cầu thật) hoặc mất (không còn tiềm năng).
3. Đầu mối đủ tiêu chuẩn có thể được chuyển thành cơ hội bán hàng để theo dõi tiếp.

**Vai trò:** Nhân viên bán hàng.

**Nối với:** cơ hội bán hàng (mục 4.3) có thể gắn với một đầu mối nguồn.

### 4.3. Cơ hội bán hàng

Cơ hội bán hàng là một thương vụ đang theo đuổi với một khách hàng (hoặc đầu mối), được quản lý trên bảng kéo thả theo từng giai đoạn: đầu mối → đủ tiêu chuẩn → đã gửi đề xuất → đang đàm phán → thắng/thua. Mỗi cơ hội có giá trị dự kiến, ngày dự kiến chốt và người phụ trách.

**Các bước**

1. Nhân viên bán hàng tạo cơ hội mới: gắn khách hàng (hoặc đầu mối), đặt tên thương vụ, giá trị dự kiến, ngày dự kiến chốt và người phụ trách.
2. Trong quá trình theo đuổi, kéo cơ hội sang giai đoạn tiếp theo trên bảng (ví dụ từ "đã gửi đề xuất" sang "đang đàm phán").
3. Khi chốt được thương vụ thì chuyển sang giai đoạn thắng; khi mất thương vụ thì chuyển sang thua.
4. Cơ hội có thể sửa thông tin hoặc xóa khi không còn theo dõi.

**Vai trò:** Nhân viên bán hàng.

**Quy tắc**

- Cơ hội di chuyển tự do giữa các giai đoạn, không bắt buộc theo thứ tự.
- Giá trị dự kiến và ngày chốt dự kiến dùng để theo dõi tiến độ bán hàng, không ảnh hưởng đến chứng từ bán hàng thực tế.

**Nối với:** báo giá (mục 4.4) có thể gắn với một cơ hội; khách hàng (mục 4.1).

### 4.4. Báo giá

Báo giá là văn bản chào giá gửi khách hàng, gồm các dòng sản phẩm kèm số lượng, đơn giá, chiết khấu theo dòng và thuế suất, cùng chiết khấu chung cho toàn bộ báo giá và ngày hết hiệu lực. Hệ thống tự tính tiền hàng, tiền thuế và tổng tiền.

**Các bước**

1. Nhân viên bán hàng lập báo giá ở trạng thái nháp: chọn khách hàng (và cơ hội nếu có), nhập các dòng sản phẩm, chiết khấu và ngày hết hiệu lực.
2. Gửi báo giá cho khách hàng (trạng thái chuyển sang đã gửi).
3. Cập nhật kết quả theo phản hồi của khách: khách chấp nhận, khách từ chối, hoặc báo giá hết hạn.
4. Khi khách đã chấp nhận báo giá, nhân viên bán hàng chuyển báo giá thành đơn bán: toàn bộ dòng hàng, giá và chiết khấu được sao chép sang đơn bán mới ở trạng thái nháp.

**Vai trò:** Nhân viên bán hàng.

**Quy tắc**

- Chỉ sửa được báo giá ở trạng thái nháp (thêm, xóa hoặc sửa các dòng sản phẩm, chiết khấu và ngày hết hiệu lực); báo giá đã gửi trở đi thì không sửa được.
- Chỉ báo giá đã được khách chấp nhận mới chuyển được thành đơn bán.
- Báo giá đã chốt (chấp nhận, từ chối, hết hạn) thì không đổi trạng thái lại được.
- Báo giá chỉ là chào giá, chưa làm thay đổi tồn kho hay công nợ.

**Nối với:** cơ hội bán hàng (mục 4.3); đơn bán hàng (mục 4.5).

### 4.5. Đơn bán hàng

Đơn bán hàng là chứng từ bán hàng chính thức sau khi khách đã đồng ý mua, có thể lập từ báo giá đã chấp nhận hoặc nhập trực tiếp. Đơn bán ghi nhận khách hàng, kho giao hàng, các dòng sản phẩm kèm số lượng, đơn giá, chiết khấu và thuế.

**Các bước**

1. Nhân viên bán hàng lập đơn bán ở trạng thái nháp. Khi chọn kho giao hàng, hệ thống hiển thị tồn khả dụng của từng sản phẩm tại kho đó để tham khảo.
2. Đơn bán còn ở trạng thái nháp thì sửa được: thêm, xóa hoặc sửa các dòng sản phẩm. Đơn đã xác nhận trở đi thì không sửa được.
3. Nhân viên bán hàng xác nhận đơn. Hệ thống kiểm tra hai điều kiện:
   - **Hạn mức công nợ:** nếu công nợ hiện tại của khách cộng với tổng tiền đơn này vượt hạn mức công nợ của khách thì đơn không bị từ chối hẳn mà chuyển sang trạng thái chờ duyệt.
   - **Tồn kho khả dụng:** nếu kho không đủ hàng cho đơn thì hệ thống chỉ cảnh báo, vẫn cho xác nhận đơn.
4. Với đơn chờ duyệt do vượt hạn mức, Giám đốc xem xét lịch sử thanh toán của khách rồi duyệt; đơn chuyển sang trạng thái đã xác nhận.
5. Khi đơn được xác nhận, hệ thống tự động tạo phiếu giao hàng cho phần hàng đủ tồn tại kho, đồng thời tạo yêu cầu mua gợi ý cho phần hàng còn thiếu (xem mục 4.6 và 2.2).
6. Đơn bán chưa bắt đầu giao hàng và chưa lập hóa đơn thì hủy được.

**Vai trò:** Nhân viên bán hàng (lập, xác nhận, hủy); Giám đốc (duyệt đơn vượt hạn mức); Kế toán (xem).

**Quy tắc**

- Hạn mức công nợ bằng 0 nghĩa là không giới hạn — đơn không bao giờ phải chờ duyệt vì lý do công nợ.
- Chỉ sửa được đơn bán ở trạng thái nháp.
- Thiếu tồn kho không chặn việc xác nhận đơn; phần thiếu sẽ được giao sau khi có hàng bổ sung.
- Đơn đã bắt đầu giao hàng hoặc đã lập hóa đơn thì không hủy được.
- Nhân viên bán hàng không được duyệt đơn vượt hạn mức — chỉ Giám đốc duyệt. Người tạo đơn không được duyệt đơn của chính mình; mỗi lần duyệt đều ghi lại người duyệt và thời điểm.

**Nối với:** báo giá (mục 4.4); giao hàng (mục 4.6); tồn kho (mục 3.3); hóa đơn bán (mục 1.4); công nợ khách hàng (mục 4.1).

### 4.6. Giao hàng

Phiếu giao hàng ghi nhận việc xuất hàng từ kho để giao cho khách theo đơn bán. Phiếu giao được hệ thống tự tạo khi đơn bán được xác nhận, với số lượng theo phần hàng đủ tồn tại kho. Phần hàng còn thiếu được giao sau bằng phiếu giao bổ sung khi đã có hàng (xem bước 4).

**Các bước**

1. Khi đơn bán được xác nhận, hệ thống tự tạo phiếu giao hàng ở trạng thái nháp cho phần hàng đủ tồn. Phần hàng thiếu tồn không có trong phiếu giao mà được chuyển thành gợi ý mua hàng bổ sung.
2. Thủ kho kiểm tra hàng thực tế và xác nhận giao hàng. Hệ thống xuất kho thật từng dòng theo giá vốn bình quân hiện tại và ghi nhận giá vốn của phiếu giao.
3. Khi phiếu giao được xác nhận, hệ thống tự động:
   - Cập nhật số lượng đã giao và trạng thái của đơn bán: còn thiếu hàng thì ở trạng thái giao một phần; giao đủ hết thì chuyển sang đã giao đủ.
   - Sinh bút toán giá vốn: ghi nhận chi phí giá vốn hàng bán và giảm giá trị hàng tồn kho.
4. Khi đơn còn thiếu hàng (trạng thái giao một phần), sau khi hàng bổ sung đã về kho, người phụ trách bấm giao phần còn lại trên đơn bán để tạo phiếu giao bổ sung cho đúng phần thiếu, rồi xác nhận giao như phiếu giao thường — không cần lập đơn bán mới. Hệ thống trừ đi số lượng đã nằm trong các phiếu giao nháp chưa xác nhận nên không tạo trùng hay giao vượt số lượng đã đặt.
5. Phiếu giao còn ở trạng thái nháp thì hủy được.

**Vai trò:** Thủ kho (tạo phiếu giao bổ sung, xác nhận, hủy phiếu giao); Nhân viên bán hàng (tạo phiếu giao bổ sung, xem); Giám đốc (xem).

**Quy tắc**

- Chỉ xác nhận được phiếu giao ở trạng thái nháp.
- Lúc xác nhận, nếu tồn kho thực tế không đủ cho phiếu giao (ví dụ hàng vừa bị xuất cho việc khác) thì hệ thống chặn lại và phiếu giữ nguyên trạng thái nháp.
- Phiếu giao bổ sung chỉ tạo được cho phần hàng còn thiếu của đơn đang giao một phần; hệ thống không tạo phiếu vượt số lượng đặt mua còn thiếu.

**Nối với:** đơn bán (mục 4.5); tồn kho và giá vốn (mục 3.3, 3.6); bút toán giá vốn (mục 1.3); hóa đơn bán (mục 1.4).

### 4.7. Bảng giá

Bảng giá là danh mục giá bán tham khảo theo từng nhóm đối tượng hoặc từng đợt (ví dụ bảng giá sỉ, bảng giá lẻ, bảng giá khuyến mãi theo thời gian). Mỗi bảng giá gồm các mặt hàng kèm giá áp dụng.

**Các bước**

1. Nhân viên bán hàng tạo bảng giá mới: đặt tên, thời gian áp dụng và nhập giá từng mặt hàng.
2. Bảng giá không dùng nữa thì xóa được.

**Vai trò:** Nhân viên bán hàng.

**Quy tắc**

- Bảng giá trong bản hiện tại chỉ để tra cứu tham khảo; khi lập báo giá hay đơn bán, đơn giá vẫn nhập trực tiếp trên từng dòng chứ hệ thống chưa tự lấy giá từ bảng giá.

---

## 5. Sản xuất

### 5.1. Định mức nguyên vật liệu (BOM)

Định mức nguyên vật liệu (BOM) là công thức sản xuất của một thành phẩm: để làm ra một đơn vị thành phẩm cần những nguyên vật liệu nào, mỗi loại bao nhiêu, và tỷ lệ hao hụt cho phép là bao nhiêu phần trăm. Định mức có thể nhiều cấp: một nguyên liệu thành phần lại có thể là bán thành phẩm với định mức riêng của nó.

**Các bước**

1. Quản trị hệ thống tạo định mức mới cho một thành phẩm: chọn thành phẩm, đặt phiên bản định mức, nhập các dòng nguyên liệu kèm số lượng cho một đơn vị thành phẩm và tỷ lệ hao hụt.
2. Định mức có thể đặt trạng thái đang áp dụng hoặc ngừng áp dụng. Khi tính nhu cầu nguyên liệu, hệ thống chỉ dùng định mức đang áp dụng.

**Vai trò:** Quản trị hệ thống (quản lý định mức); Giám đốc và Nhân viên sản xuất (xem).

**Quy tắc**

- Định mức được triển khai tối đa 5 cấp khi tính nhu cầu nguyên liệu (để tránh vòng lặp khi định mức lồng nhau).
- Tỷ lệ hao hụt được cộng thêm vào nhu cầu nguyên liệu (ví dụ hao hụt 5% thì cần 105 đơn vị nguyên liệu cho 100 đơn vị thành phẩm).

**Nối với:** lệnh sản xuất (mục 5.4) dùng định mức để tính nguyên liệu cần thiết.

### 5.2. Trung tâm sản xuất

Trung tâm sản xuất là các tổ, chuyền hoặc máy móc nơi thực hiện các công đoạn sản xuất (ví dụ tổ cắt, tổ may, tổ đóng gói). Mỗi trung tâm có mã, tên, chi phí theo giờ và trạng thái hoạt động.

**Các bước**

1. Quản trị hệ thống tạo trung tâm mới với mã, tên và chi phí theo giờ.
2. Sửa thông tin hoặc đánh dấu ngừng hoạt động khi trung tâm không dùng nữa.

**Vai trò:** Quản trị hệ thống (quản lý trung tâm sản xuất); Giám đốc và Nhân viên sản xuất (xem).

**Quy tắc**

- Chi phí theo giờ của trung tâm hiện chỉ ghi nhận để tham khảo, chưa đưa vào giá thành sản phẩm (giá thành hiện chỉ tính theo chi phí nguyên vật liệu — xem mục 5.4).

**Nối với:** quy trình sản xuất (mục 5.3) gắn các công đoạn với trung tâm thực hiện; công đoạn sản xuất (mục 5.5).

### 5.3. Quy trình sản xuất

Quy trình sản xuất là trình tự các công đoạn cần thực hiện để làm ra một thành phẩm: mỗi công đoạn làm việc gì, thực hiện tại trung tâm sản xuất nào, theo thứ tự nào và mất bao nhiêu thời gian dự kiến. Lệnh sản xuất gắn với một quy trình sẽ tự sinh ra các công đoạn theo quy trình đó khi bắt đầu sản xuất.

**Các bước**

1. Quản trị hệ thống tạo quy trình mới cho một thành phẩm: thêm từng công đoạn theo thứ tự thực hiện, mỗi công đoạn gắn với một trung tâm sản xuất và thời gian dự kiến.
2. Khi lệnh sản xuất gắn với quy trình này được bắt đầu, hệ thống tự tạo các công đoạn theo đúng thứ tự trong quy trình.

**Vai trò:** Quản trị hệ thống (quản lý quy trình); Giám đốc và Nhân viên sản xuất (xem).

**Quy tắc**

- Lệnh sản xuất không gắn quy trình thì khi bắt đầu sẽ không sinh ra công đoạn nào.
- Thứ tự công đoạn trong quy trình là thứ tự thực hiện khi sản xuất.

**Nối với:** trung tâm sản xuất (mục 5.2); lệnh sản xuất (mục 5.4) dùng quy trình để sinh công đoạn; công đoạn sản xuất (mục 5.5).

Dữ liệu dùng thử đã có sẵn trung tâm sản xuất, định mức, quy trình và một lệnh sản xuất nháp để xem và thử nghiệm luồng sản xuất.

### 5.4. Lệnh sản xuất

Lệnh sản xuất là kế hoạch sản xuất một số lượng thành phẩm nhất định, có thể lập từ đơn bán hàng hoặc lập trực tiếp. Lệnh sản xuất gắn với định mức nguyên vật liệu, kho xuất nguyên liệu và nhập thành phẩm, ngày dự kiến bắt đầu và kết thúc.

**Các bước**

1. Nhân viên sản xuất lập lệnh ở trạng thái nháp: chọn thành phẩm, định mức áp dụng, quy trình sản xuất (nếu có), số lượng cần sản xuất, kho và ngày dự kiến.
2. Xác nhận lệnh sản xuất.
3. Bắt đầu sản xuất. Hệ thống kiểm tra nguyên liệu theo định mức (triển khai nhiều cấp, đã cộng hao hụt) so với tồn khả dụng tại kho:
   - Nếu đủ nguyên liệu: lệnh chuyển sang trạng thái đang thực hiện; các công đoạn được tạo ra theo quy trình sản xuất (nếu lệnh có gắn quy trình).
   - Nếu thiếu nguyên liệu: hệ thống tự tạo yêu cầu mua gợi ý cho phần thiếu và dừng lại — lệnh chưa được bắt đầu cho đến khi đủ nguyên liệu.
4. Ghi nhận tiến độ từng phần trong khi lệnh đang chạy: mỗi đợt sản xuất xong, nhân viên sản xuất ghi đợt này làm được bao nhiêu thành phẩm và hỏng bao nhiêu phế phẩm. Mỗi lần ghi, hệ thống xuất kho nguyên liệu và nhập kho thành phẩm tương ứng với đợt đó, cập nhật số lượng đã sản xuất lũy kế và ghi nhật ký sản xuất; lệnh vẫn ở trạng thái đang thực hiện cho đến khi bấm hoàn thành. Ví dụ lệnh 100 cái có thể ghi 40 cái hôm nay, 30 cái ngày mai, rồi hoàn thành nốt 30 cái còn lại.
5. Hoàn thành sản xuất: nhập số lượng thành phẩm và phế phẩm của đợt cuối (nếu còn). Hệ thống chỉ xử lý phần chênh lệch còn lại so với các đợt đã ghi tiến độ — không xuất trùng nguyên liệu đã xuất ở các đợt trước — rồi đóng lệnh và đóng các công đoạn còn dở.
6. Lệnh chưa bắt đầu sản xuất thì hủy được.

**Vai trò:** Nhân viên sản xuất (lập, xác nhận, bắt đầu, ghi tiến độ, hoàn thành, hủy lệnh sản xuất); Giám đốc (xem).

**Quy tắc**

- Lệnh chưa chọn kho thì không bắt đầu và không hoàn thành được.
- Thiếu nguyên liệu thì không bắt đầu được; phải bổ sung nguyên liệu rồi bắt đầu lại.
- Khi ghi tiến độ hoặc hoàn thành, hệ thống kiểm tra tồn nguyên liệu: thiếu nguyên liệu thì báo rõ tên nguyên liệu còn thiếu và không ghi nhận đợt đó.
- Giá thành phẩm chỉ tính theo chi phí nguyên vật liệu; chi phí nhân công và chi phí trung tâm sản xuất chưa đưa vào giá thành trong bản hiện tại.
- Nguyên liệu xuất kho được tính theo định mức nhân với sản lượng (gồm cả thành phẩm và phế phẩm); bản hiện tại chưa cho nhập số nguyên liệu thực tế đã tiêu hao nếu khác định mức.

**Lưu ý:** hoàn thành sản xuất và ghi tiến độ chỉ làm thay đổi tồn kho (xuất nguyên liệu, nhập thành phẩm); bản hiện tại chưa tự sinh bút toán kế toán cho nghiệp vụ sản xuất.

**Nối với:** định mức (mục 5.1); quy trình sản xuất (mục 5.3); tồn kho (mục 3.3) — xuất nguyên liệu và nhập thành phẩm; yêu cầu mua gợi ý (mục 2.2) khi thiếu nguyên liệu; công đoạn (mục 5.5).

### 5.5. Công đoạn sản xuất

Công đoạn là từng bước gia công trong một lệnh sản xuất (ví dụ cắt, may, đóng gói), mỗi công đoạn gắn với một trung tâm sản xuất, có thứ tự thực hiện, thời gian dự kiến và thời gian thực tế.

**Các bước**

1. Khi lệnh sản xuất bắt đầu và lệnh có gắn quy trình sản xuất, hệ thống tự tạo các công đoạn theo quy trình ở trạng thái chờ thực hiện.
2. Trong quá trình sản xuất, nhân viên sản xuất cập nhật trạng thái từng công đoạn (đang thực hiện, hoàn thành, hủy) và ghi số phút thực tế đã làm cho công đoạn đó.
3. Nhân viên sản xuất cũng có thể thêm công đoạn mới (ví dụ bước kiểm tra chất lượng phát sinh), sửa tên/trung tâm/thứ tự/thời gian của công đoạn, hoặc xóa công đoạn thừa — ngay trên trang Công đoạn hoặc trong trang chi tiết lệnh sản xuất.
4. Khi lệnh sản xuất hoàn thành, các công đoạn còn dở được tự động đóng lại.

**Quy tắc**

- Chỉ thêm/sửa/xóa công đoạn khi lệnh đang thực hiện. Lệnh còn nháp chưa có công đoạn; lệnh đã xong hoặc đã hủy thì khóa lại để giữ lịch sử.
- Thứ tự công đoạn là duy nhất trong mỗi lệnh (không có 2 công đoạn cùng số thứ tự).
- Không xóa được công đoạn đã hoàn thành hoặc đã có nhật ký sản xuất.

**Vai trò:** Nhân viên sản xuất (thêm/sửa/xóa và cập nhật công đoạn); Giám đốc (xem).

**Nối với:** lệnh sản xuất (mục 5.4); quy trình sản xuất (mục 5.3); trung tâm sản xuất (mục 5.2).

---

## 6. Nhân sự và lương

### 6.1. Phòng ban và chức danh

Phòng ban là các đơn vị tổ chức trong công ty (bản dùng thử có phòng Kế toán, phòng Bán hàng và phòng Kho). Mỗi phòng ban có mã, tên, người quản lý và trạng thái hoạt động. Chức danh (ví dụ kế toán viên, nhân viên) gắn với từng nhân viên trong hồ sơ của họ.

**Các bước**

1. Nhân sự tạo phòng ban mới với mã, tên và người quản lý.
2. Phòng ban không dùng nữa được đánh dấu ngừng hoạt động.

**Vai trò:** Nhân sự.

**Quy tắc**

- Chức danh đã được thiết lập sẵn trong dữ liệu dùng thử; bản hiện tại chưa có màn hình tự thêm chức danh mới.
- Mỗi nhân viên thuộc một phòng ban và một chức danh (xem mục 6.2).

### 6.2. Hồ sơ nhân viên

Hồ sơ nhân viên lưu thông tin của từng người lao động: mã nhân viên, họ tên, email, điện thoại, phòng ban, chức danh, ngày vào làm, ngày nghỉ việc (nếu đã nghỉ), trạng thái đang làm việc hay đã nghỉ, lương cơ bản, số người phụ thuộc (để tính giảm trừ gia cảnh khi tính thuế thu nhập cá nhân) và tài khoản đăng nhập gắn kèm (nếu có).

**Các bước**

1. Nhân sự tạo hồ sơ nhân viên mới khi tuyển dụng. Hồ sơ cũng có thể được tạo kèm ngay khi quản trị hệ thống tạo tài khoản đăng nhập cho nhân viên đó (xem mục 0.5).
2. Khi có thay đổi (chuyển phòng ban, đổi chức danh, điều chỉnh lương cơ bản, thay đổi số người phụ thuộc), nhân sự cập nhật hồ sơ.
3. Nhân viên nghỉ việc được chuyển trạng thái sang đã nghỉ bằng nút cho nghỉ việc (đồng thời ghi ngày nghỉ việc); nếu nhân viên quay lại làm việc thì kích hoạt lại. Hồ sơ nhân viên có thể xóa khỏi danh sách khi không dùng nữa — hệ thống chỉ ẩn hồ sơ và vẫn khôi phục được khi cần.

**Vai trò:** Nhân sự (quản lý); Giám đốc (xem).

**Quy tắc**

- Chỉ nhân viên đang làm việc mới được tính lương đủ tháng trong kỳ lương (mục 6.5). Nhân viên vào làm hoặc nghỉ việc giữa tháng chỉ tính lương cho phần tháng đã làm việc, căn cứ theo ngày vào làm và ngày nghỉ việc trong hồ sơ.
- Mã nhân viên là duy nhất, dùng để tra cứu nhanh trong chấm công, nghỉ phép và bảng lương.
- Hồ sơ nhân viên đã xóa vẫn khôi phục được từ danh sách hồ sơ đã xóa.

**Nối với:** chấm công (mục 6.3), nghỉ phép (mục 6.4), bảng lương (mục 6.5).

**Lưu ý:** hợp đồng lao động và ca làm việc đã có cấu trúc dữ liệu trong hệ thống (bảng tổng quan có cảnh báo hợp đồng sắp hết hạn), nhưng bản hiện tại chưa có màn hình quản lý hợp đồng hay ca làm việc.

### 6.3. Chấm công

Chấm công ghi nhận ngày công thực tế của từng nhân viên: ngày làm việc, ca làm việc (nếu có), giờ vào, giờ ra và trạng thái ngày công (đi làm, đi muộn, vắng mặt, nghỉ phép).

**Các bước**

1. Nhân viên tự chấm công vào và ra trong ngày làm việc của mình trên mục chấm công cá nhân. Chỉ chấm được trong ngày hiện tại, không sửa được giờ đã chấm.
2. Nhân sự cũng có thể nhập hoặc điều chỉnh bản ghi chấm công cho từng nhân viên theo ngày: chọn nhân viên, ngày làm việc, ca (nếu có), giờ vào, giờ ra và trạng thái.
3. Nếu đã có bản ghi chấm công của nhân viên trong ngày đó thì lần nhập sau sẽ cập nhật lên bản ghi cũ thay vì tạo trùng.
4. Nhân sự xem lại bảng chấm công theo nhân viên và theo ngày để đối chiếu; nhân viên chỉ xem được chấm công của chính mình trên lịch tháng của mình: lịch hiển thị giờ vào/ra từng ngày, đánh dấu ngày nghỉ phép đã duyệt, ngày thiếu chấm công (ngày làm việc đã qua mà không có bản ghi chấm công và không có đơn nghỉ) và ngày chưa chấm ra; cuối tuần được tô xám để phân biệt với ngày làm việc.

**Vai trò:** Nhân viên (tự chấm công và xem chấm công của mình); Nhân sự (nhập, điều chỉnh và xem); Giám đốc (xem).

**Quy tắc**

- Giờ ra phải sau giờ vào.
- Bản hiện tại chỉ nhập chấm công bằng tay; chưa nhập tự động từ máy chấm công.
- Dữ liệu chấm công được dùng để tính ngày công trong bảng lương (mục 6.5): vắng mặt không phép và nghỉ không lương làm giảm lương tháng; đi muộn vẫn tính đủ công.
- Hệ thống chưa có lịch nghỉ lễ: vào ngày lễ, nhân sự cần tạo đơn nghỉ hoặc bản ghi nghỉ cho nhân viên thì lịch chấm công mới không báo thiếu chấm công ngày đó.

### 6.4. Nghỉ phép

Nghỉ phép gồm ba loại: nghỉ phép năm (trừ vào số ngày phép được hưởng), nghỉ ốm và nghỉ không lương (không trừ phép năm). Mỗi nhân viên có 12 ngày phép năm mỗi năm theo mặc định. Số ngày nghỉ chỉ tính ngày làm việc từ thứ 2 đến thứ 6, không tính thứ 7 và chủ nhật.

**Các bước**

1. Nhân viên tự đăng ký nghỉ cho mình, hoặc nhân sự đăng ký nghỉ thay cho nhân viên: chọn loại nghỉ, ngày bắt đầu, ngày kết thúc và lý do. Hệ thống tính số ngày nghỉ theo ngày làm việc (thứ 2 đến thứ 6, tính cả ngày bắt đầu và ngày kết thúc). Đơn nghỉ ở trạng thái chờ xử lý.
2. Người có quyền duyệt sẽ duyệt hoặc từ chối đơn nghỉ.
3. Khi đơn nghỉ phép năm được duyệt, số ngày nghỉ được trừ vào số dư phép năm của nhân viên trong năm đó.
4. Đơn nghỉ còn chờ xử lý thì hủy được.

**Vai trò:** Nhân viên (tự đăng ký nghỉ của mình); Nhân sự (đăng ký nghỉ thay cho nhân viên); Giám đốc và Nhân sự (duyệt đơn nghỉ).

**Quy tắc**

- Đăng ký nghỉ phép năm vượt số ngày phép còn lại sẽ bị từ chối ngay lúc đăng ký.
- Đơn nghỉ chỉ toàn ngày thứ 7/chủ nhật (không có ngày làm việc nào) thì không tạo được.
- Khi duyệt, hệ thống kiểm tra lại số dư phép: nếu không đủ phép thì không duyệt được.
- Chỉ nghỉ phép năm mới trừ vào số dư phép; nghỉ ốm và nghỉ không lương không trừ.
- Từ chối hoặc hủy đơn nghỉ không làm thay đổi số dư phép.
- Đơn nghỉ đã duyệt ảnh hưởng đến lương tháng (mục 6.5): ngày nghỉ không lương bị trừ lương, ngày nghỉ phép năm và nghỉ ốm vẫn hưởng lương.
- Khi đơn nghỉ được duyệt, hệ thống ghi nhận các ngày nghỉ đó vào chấm công để lịch chấm công hiển thị đúng.

**Nối với:** hồ sơ nhân viên (mục 6.2); chấm công (mục 6.3); bảng lương (mục 6.5); số đơn nghỉ chờ duyệt hiển thị trên bảng tổng quan (mục 7.1).

### 6.5. Bảng lương

Bảng lương là kỳ tính lương hàng tháng cho toàn bộ nhân viên, gồm lương cơ bản theo ngày công thực tế, phụ cấp, khấu trừ, bảo hiểm và thuế thu nhập cá nhân, cho ra lương thực lĩnh của từng nhân viên.

**Công thức tính lương (theo luật từ 1/7/2026)**

- Ngày công chuẩn của tháng = số ngày làm việc từ thứ 2 đến thứ 6 trong tháng đó. Ngày công hưởng lương = ngày công chuẩn trừ đi ngày nghỉ không lương và ngày vắng mặt không phép trong tháng (ngày nghỉ phép năm và nghỉ ốm vẫn hưởng lương; đi muộn vẫn tính đủ công).
- Lương cơ bản theo ngày công = lương cơ bản × (ngày công hưởng lương / ngày công chuẩn). Nhân viên vào làm hoặc nghỉ việc giữa tháng chỉ tính lương trong khoảng thời gian làm việc trong tháng.
- Phụ cấp cũng tính theo tỷ lệ ngày công như lương cơ bản (cả phụ cấp cố định và phụ cấp theo phần trăm lương cơ bản).
- Lương gộp = lương cơ bản theo ngày công + các khoản phụ cấp.
- Bảo hiểm phần người lao động = lương hợp đồng × (bảo hiểm xã hội 8% + bảo hiểm y tế 1,5% + bảo hiểm thất nghiệp 1%) = 10,5% lương hợp đồng, trong đó lương làm căn cứ đóng không vượt trần đóng: trần bảo hiểm xã hội và bảo hiểm y tế 50.600.000 đồng/tháng, trần bảo hiểm thất nghiệp 106.200.000 đồng/tháng (theo lương tối thiểu vùng I). Tháng nào nhân viên nghỉ không lương từ 14 ngày làm việc trở lên thì tháng đó không đóng bảo hiểm xã hội, bảo hiểm y tế và bảo hiểm thất nghiệp.
- Thu nhập tính thuế = lương gộp − khấu trừ − bảo hiểm phần người lao động − giảm trừ bản thân 15.500.000 đồng/tháng − giảm trừ mỗi người phụ thuộc 6.200.000 đồng/tháng (theo Luật Thuế thu nhập cá nhân 109/2025/QH15, hiệu lực từ 1/7/2026).
- Thuế thu nhập cá nhân tính theo biểu lũy tiến từng phần 5 bậc: đến 10 triệu đồng 5%, trên 10 đến 30 triệu đồng 10%, trên 30 đến 60 triệu đồng 20%, trên 60 đến 100 triệu đồng 30%, trên 100 triệu đồng 35%.
- Lương thực lĩnh = lương gộp − khấu trừ − bảo hiểm phần người lao động − thuế thu nhập cá nhân.
- Bảo hiểm phần công ty đóng (bảo hiểm xã hội 17,5% + bảo hiểm y tế 3% + bảo hiểm thất nghiệp 1% = 21,5% lương hợp đồng, áp cùng mức trần như trên) chỉ dùng để tính tổng chi phí lương của công ty, không trừ vào lương nhân viên.
- Phiếu lương của từng nhân viên ghi rõ ngày công (ví dụ "Ngày công 20/22") để đối chiếu.

**Lưu ý quan trọng:** các mức giảm trừ, biểu thuế và mức trần bảo hiểm trên theo quy định từ 1/7/2026; mức trần bảo hiểm thất nghiệp theo lương tối thiểu vùng I. Công ty ở vùng khác hoặc khi luật thay đổi thì cần rà soát lại các mức này với kế toán hoặc chuyên gia thuế trước khi dùng để trả lương thật.

**Các bước**

1. Nhân sự tạo kỳ lương mới cho một tháng.
2. Nhân sự chạy tính lương. Hệ thống tính phiếu lương cho từng nhân viên theo công thức trên; phiếu lương chi tiết từng khoản (ngày công, phụ cấp, khấu trừ, từng loại bảo hiểm, thuế). Nếu tính lại trước khi khóa, hệ thống xóa phiếu lương cũ và tính lại từ đầu.
3. Nhân sự, Giám đốc và Kế toán xem trước bảng lương, kiểm tra tổng lương gộp, tổng khấu trừ, tổng thuế, tổng bảo hiểm và tổng thực lĩnh. Kỳ lương mới tính xong hiển thị trạng thái "Đã tính".
4. Nhân sự khóa bảng lương để chốt số liệu. Sau khi khóa, kỳ lương không tính lại và không sửa được nữa. Nhân viên bắt đầu xem được phiếu lương của mình từ khi kỳ lương đã khóa.
5. Giám đốc duyệt bảng lương đã khóa. Khi duyệt, hệ thống tự sinh bút toán lương vào sổ kế toán: ghi nhận chi phí lương (gồm cả bảo hiểm phần công ty đóng), lương thực lĩnh phải trả nhân viên, các khoản bảo hiểm phải nộp, thuế thu nhập cá nhân phải nộp và các khoản khấu trừ lương (ghi vào tài khoản phải trả khác 3388). Bút toán lương cân bằng ngay cả khi có phụ cấp và khấu trừ.
6. Sau khi chi trả lương thực tế cho nhân viên, Kế toán đánh dấu kỳ lương đã trả để hoàn tất quy trình.

**Vai trò:** Nhân sự (tạo kỳ, chạy tính lương, khóa bảng lương); Giám đốc (duyệt bảng lương); Kế toán (xem bảng lương để đối chiếu bút toán lương, đánh dấu đã trả lương); Nhân viên (xem phiếu lương của chính mình từ khi kỳ đã khóa).

**Quy tắc**

- Chỉ tính lương cho nhân viên đang làm việc, cộng với nhân viên nghỉ việc trong tháng đó (tính cho phần tháng đã làm việc).
- Nhân sự tính và khóa bảng lương nhưng không duyệt được bảng lương do chính mình tính — việc duyệt do Giám đốc thực hiện.
- Kế toán không tạo kỳ, không chạy tính lương, không khóa và không duyệt bảng lương; kế toán chỉ đánh dấu đã trả sau khi bảng lương đã được duyệt.
- Nhân viên chỉ xem được phiếu lương của chính mình, không xem được phiếu lương của người khác, và chỉ xem được từ khi kỳ lương đã khóa trở đi.
- Chỉ khóa được kỳ lương đã tính xong; chỉ duyệt được kỳ lương đã khóa; chỉ đánh dấu đã trả được kỳ lương đã duyệt.
- Kỳ lương đã khóa thì không tính lại được.
- Các khoản phụ cấp và khấu trừ được tính theo danh mục khoản lương đang có hiệu lực; bản dùng thử chưa cấu hình khoản phụ cấp hay khấu trừ nào nên lương gộp hiện bằng lương cơ bản theo ngày công.

**Nối với:** hồ sơ nhân viên (mục 6.2) cung cấp lương cơ bản, số người phụ thuộc, ngày vào làm và ngày nghỉ việc; chấm công (mục 6.3) và nghỉ phép (mục 6.4) cung cấp ngày công thực tế; bút toán lương (mục 1.3) được sinh khi duyệt bảng lương.

---

## 7. Báo cáo

### 7.1. Bảng tổng quan

Bảng tổng quan là màn hình đầu tiên sau khi đăng nhập, hiển thị các chỉ số chính của công ty trong tháng, các cảnh báo cần chú ý và biểu đồ doanh thu 6 tháng gần nhất. Số liệu tổng quan được làm mới vài phút một lần.

**Các chỉ số chính**

- Doanh thu tháng này (từ các hóa đơn bán đã ghi sổ và đã thanh toán trong tháng).
- Số đơn hàng trong tháng.
- Giá trị tồn kho (tổng số lượng tồn nhân với giá vốn bình quân của từng sản phẩm).
- Công nợ phải thu (số dư nợ tài khoản 131 trên sổ cái — đã trừ tiền khách đã trả).
- Công nợ phải trả (số dư có tài khoản 331 trên sổ cái — đã trừ tiền đã trả nhà cung cấp).
- Số nhân viên đang làm việc.
- Số đơn nghỉ phép đang chờ duyệt.

**Các cảnh báo**

Mỗi cảnh báo đều bấm được để mở ngay trang xử lý tương ứng:

- Sản phẩm tồn dưới mức tối thiểu (tối đa 5 sản phẩm thiếu nhiều nhất). Mỗi dòng ghi rõ số lượng hiện có, mức tồn tối thiểu và số lượng còn thiếu; bấm vào sản phẩm để xem tồn kho, bấm đường dẫn lập yêu cầu mua để đặt mua bổ sung.
- Hóa đơn bán quá hạn thanh toán (tối đa 5 hóa đơn quá hạn lâu nhất). Mỗi dòng ghi rõ khách hàng, số ngày đã quá hạn và số tiền; bấm vào hóa đơn để mở chi tiết hóa đơn và ghi nhận thanh toán.
- Phiếu nhận hàng đang chờ xác nhận (tối đa 5 phiếu). Bấm vào để mở danh sách phiếu nhận và xác nhận.
- Hợp đồng lao động sắp hết hạn trong 30 ngày tới (tối đa 5 hợp đồng). Mỗi dòng ghi rõ số ngày còn lại trước ngày hết hạn; bấm vào để mở hồ sơ nhân viên và xem hợp đồng.
- Khi số mục cảnh báo vượt quá 5, bảng tổng quan ghi rõ đang hiển thị 5 trên tổng số bao nhiêu mục để biết còn bao nhiêu mục chưa hiển thị.

**Biểu đồ:** doanh thu và số đơn hàng của 6 tháng gần nhất.

**Vai trò và giới hạn hiển thị:** Mọi vai trò đều mở được bảng tổng quan, nhưng mỗi chỉ số và cảnh báo chỉ hiện với vai trò có quyền xem nội dung tương ứng:

- Doanh thu, số đơn hàng và biểu đồ doanh thu: chỉ hiện với vai trò được xem đơn bán hoặc báo cáo (Giám đốc, Kế toán, Nhân viên bán hàng, Nhân sự).
- Giá trị tồn kho và cảnh báo hàng sắp hết: chỉ hiện với vai trò được xem tồn kho (Giám đốc, Thủ kho, Nhân viên bán hàng).
- Công nợ phải thu và cảnh báo hóa đơn quá hạn: chỉ hiện với Kế toán và Giám đốc.
- Công nợ phải trả: chỉ hiện với Kế toán và Giám đốc.
- Cảnh báo phiếu nhận chờ xác nhận: chỉ hiện với người có quyền xác nhận phiếu nhận (Giám đốc).
- Số nhân viên, cảnh báo hợp đồng sắp hết hạn và số đơn nghỉ chờ duyệt: chỉ hiện với Nhân sự và Giám đốc.
- Vai trò Nhân viên không thấy các chỉ số và cảnh báo trên; nhân viên dùng mục cá nhân để xem chấm công, nghỉ phép và phiếu lương của mình.

Nói cách khác, Thủ kho chỉ thấy chỉ số tồn kho và cảnh báo hàng sắp hết; Nhân sự thấy doanh thu và đơn hàng (nhờ quyền xem báo cáo) cùng các chỉ số nhân sự; Kế toán không thấy chỉ số tồn kho hay nhân sự; Nhân viên không thấy các chỉ số và cảnh báo này.

### 7.2. Doanh thu theo khách hàng

Báo cáo liệt kê doanh thu theo từng khách hàng trong một khoảng thời gian: số hóa đơn bán đã ghi sổ hoặc đã thanh toán, tổng doanh thu, sắp xếp từ khách mua nhiều nhất xuống ít nhất.

**Các bước**

1. Người xem chọn khoảng thời gian cần xem.
2. Hệ thống liệt kê doanh thu theo từng khách hàng trong khoảng thời gian đó.
3. Có thể xuất kết quả ra tập tin CSV để lưu trữ hoặc xử lý thêm.

**Vai trò:** Giám đốc, Kế toán, Nhân viên bán hàng, Nhân sự.

**Quy tắc**

- Chỉ tính các hóa đơn bán đã ghi sổ hoặc đã thanh toán; hóa đơn nháp và hóa đơn đã hủy không được tính.

### 7.3. Công nợ tuổi nợ

Báo cáo phân loại các hóa đơn bán chưa thanh toán hết theo thời gian quá hạn so với hạn thanh toán: 0–30 ngày, 31–60 ngày, 61–90 ngày và trên 90 ngày. Hóa đơn chưa tới hạn thanh toán được xếp vào nhóm 0–30 ngày.

**Các bước**

1. Người xem mở báo cáo công nợ tuổi nợ.
2. Hệ thống liệt kê từng nhóm tuổi nợ gồm số hóa đơn, tổng tiền và chi tiết từng hóa đơn (khách hàng, ngày hóa đơn, hạn thanh toán, số ngày quá hạn, số tiền).
3. Có thể xuất kết quả ra tập tin CSV.

**Vai trò:** Giám đốc, Kế toán, Nhân viên bán hàng, Nhân sự.

**Nối với:** hóa đơn bán (mục 1.4) và khách hàng (mục 4.1); công nợ phải trả xem ở báo cáo công nợ phải trả (mục 7.4).

### 7.4. Công nợ phải trả theo nhà cung cấp

Báo cáo liệt kê công nợ phải trả theo từng nhà cung cấp, đọc trực tiếp từ sổ cái tài khoản 331 (số dư có trừ nợ): nhà cung cấp nào công ty còn nợ bao nhiêu, chia nhóm tuổi nợ theo ngày phát sinh bút toán — 0–30 ngày, 31–60 ngày, 61–90 ngày và trên 90 ngày.

**Các bước**

1. Người xem mở báo cáo công nợ phải trả.
2. Hệ thống liệt kê từng nhà cung cấp còn số dư phải trả kèm tổng phải trả và số tiền theo từng nhóm tuổi nợ.
3. Trong cùng báo cáo, mục "Hàng đã nhận, chưa có hóa đơn (TK 335)" liệt kê riêng phần hàng đã nhận về kho nhưng kế toán chưa lập hóa đơn mua — phần này chưa tính vào công nợ phải trả 331; khi hóa đơn mua từ phiếu nhận được ghi sổ thì số tiền chuyển từ 335 sang 331.
4. Có thể xuất kết quả ra tập tin CSV.

**Vai trò:** Giám đốc, Kế toán.

**Quy tắc**

- Chỉ tính các bút toán đã ghi sổ trên tài khoản 331; nhà cung cấp đã thanh toán hết thì không xuất hiện trong báo cáo.
- Tuổi nợ tính theo ngày phát sinh bút toán, không theo hạn thanh toán trên hóa đơn.

**Nối với:** phiếu nhận hàng (mục 2.5) — hàng đã nhận chưa có hóa đơn nằm ở mục 335; hóa đơn mua (mục 1.4); công nợ phải thu xem ở báo cáo công nợ tuổi nợ (mục 7.3).

### 7.5. Xuất nhập tồn

Báo cáo xuất nhập tồn liệt kê theo từng sản phẩm trong một khoảng thời gian: tồn đầu kỳ, số lượng nhập trong kỳ, số lượng xuất trong kỳ và tồn cuối kỳ. Có thể lọc theo một kho cụ thể hoặc xem toàn bộ các kho.

**Các bước**

1. Người xem chọn khoảng thời gian và kho (nếu cần).
2. Hệ thống liệt kê số liệu xuất nhập tồn theo từng sản phẩm.
3. Có thể xuất kết quả ra tập tin CSV.

**Vai trò:** Giám đốc, Kế toán, Nhân viên bán hàng, Nhân sự. Thủ kho không dùng báo cáo này mà xem tồn kho trên màn hình kho.

**Lưu ý:** số tồn đầu kỳ và tồn cuối kỳ trong báo cáo này được tính đơn giản từ số liệu tổng hợp theo ngày; khi dữ liệu tổng hợp chưa có, báo cáo tính trực tiếp từ sổ kho và tồn đầu kỳ hiển thị bằng 0.

### 7.6. Xuất dữ liệu

Cả bốn báo cáo trên đều có thể xuất ra tập tin CSV để tải về và mở bằng chương trình bảng tính. Bản hiện tại chưa xuất được tập tin PDF hay Excel, và chưa có tính năng lên lịch gửi báo cáo định kỳ qua thư điện tử.

---

## 8. Luồng nghiệp vụ đầu-cuối tổng hợp

### 8.1. Luồng bán hàng trọn vẹn (ví dụ: bán 100 thùng nước ép cho Công ty Minh Long)

Ví dụ theo bản thiết kế: công ty bán 100 thùng nước ép cho khách hàng Công ty Minh Long, giao từ kho Hà Nội.

1. **Báo giá và đơn bán.** Nhân viên bán hàng Hoa lập báo giá 100 thùng nước ép gửi khách hàng. Khách đồng ý, Hoa chuyển báo giá thành đơn bán. Hệ thống kiểm tra công nợ của Minh Long: khách còn 30 triệu đồng trên hạn mức 50 triệu đồng, đơn này trị giá 42 triệu đồng nên vượt hạn mức — đơn chuyển sang trạng thái chờ duyệt thay vì bị từ chối.
2. **Duyệt đơn vượt hạn mức.** Giám đốc Hùng xem lịch sử thanh toán của khách rồi duyệt đơn. Đơn chuyển sang trạng thái đã xác nhận. (Người tạo đơn không tự duyệt được đơn của mình; nhân viên bán hàng cũng không có quyền duyệt đơn vượt hạn mức.)
3. **Chuẩn bị giao hàng.** Khi đơn được xác nhận, hệ thống tự kiểm tra tồn khả dụng tại kho Hà Nội: kho có 80 thùng, thiếu 20 thùng. Hệ thống tự tạo phiếu giao hàng cho 80 thùng đủ tồn, đồng thời tạo yêu cầu mua gợi ý cho 20 thùng còn thiếu.
4. **Mua bổ sung.** Nhân viên mua hàng lập đơn mua 20 thùng từ nhà cung cấp. Khi hàng về, thủ kho lập phiếu nhận hàng và đối chiếu với đơn mua; Giám đốc xác nhận phiếu nhận thì hàng mới nhập kho, làm tăng tồn và tính lại giá vốn bình quân.
5. **Giao hàng và ghi nhận giá vốn.** Thủ kho xác nhận giao 80 thùng; hệ thống xuất kho và tự sinh bút toán giá vốn hàng bán. Sau đó, khi hàng bổ sung đã về, nhân viên bán hàng bấm giao phần còn lại trên đơn bán để tạo phiếu giao bổ sung cho 20 thùng còn thiếu rồi giao tiếp, thay vì phải lập đơn bán mới.
6. **Lập hóa đơn và thu tiền.** Kế toán bấm lập hóa đơn trên đơn bán để tạo hóa đơn nháp từ phần hàng đã giao (giữ nguyên giá, thuế và chiết khấu trên đơn), kiểm tra rồi ghi sổ: ghi nhận phải thu khách hàng, doanh thu và thuế giá trị gia tăng đầu ra. Khi khách thanh toán, kế toán ghi nhận thu tiền; đủ tiền thì hóa đơn chuyển sang trạng thái đã thanh toán xong và đơn bán được đánh dấu đã lập hóa đơn.
7. **Báo cáo.** Bảng tổng quan cập nhật doanh thu trong ngày; đơn hàng xuất hiện trong báo cáo doanh thu theo khách hàng và báo cáo công nợ tuổi nợ.

### 8.2. Luồng mua hàng trọn vẹn (từ nhu cầu đến thanh toán)

1. Nhu cầu mua phát sinh: người dùng lập yêu cầu mua, hoặc hệ thống tự gợi ý yêu cầu mua khi tồn kho xuống dưới mức tối thiểu hay khi đơn bán/lệnh sản xuất thiếu hàng.
2. Yêu cầu mua được duyệt (tự động duyệt nếu tổng giá trị từ 50 triệu đồng trở xuống; Giám đốc duyệt nếu trên 50 triệu đồng).
3. Nhân viên mua hàng lập đơn mua từ yêu cầu đã duyệt (hoặc từ báo giá đã chấp nhận, hoặc lập trực tiếp), trình duyệt và được duyệt.
4. Nhà cung cấp giao hàng; thủ kho lập phiếu nhận hàng và đối chiếu số lượng với đơn mua, Giám đốc xác nhận phiếu nhận. Tồn kho tăng lên, giá vốn bình quân được tính lại, sổ kế toán ghi nhận giá trị hàng nhập vào tài khoản hàng về chưa có hóa đơn (335).
5. Kế toán lập hóa đơn mua từ phiếu nhận và ghi sổ: số tiền chuyển từ 335 sang phải trả nhà cung cấp (331). Sau đó thanh toán cho nhà cung cấp (có thể chia nhiều lần) cho đến khi hóa đơn được thanh toán xong. Công nợ phải trả theo dõi ở báo cáo công nợ phải trả.
6. Đơn mua đã nhận đủ hàng được đóng lại.

### 8.3. Luồng lương trọn vẹn

1. Trong tháng, nhân viên tự chấm công hằng ngày và tự đăng ký nghỉ phép khi cần (nhân sự cũng có thể nhập chấm công và đăng ký nghỉ thay cho nhân viên); đơn nghỉ được duyệt theo quy định.
2. Cuối tháng, nhân sự tạo kỳ lương và chạy tính lương cho toàn bộ nhân viên; hệ thống tính lương theo ngày công thực tế (trừ nghỉ không lương và vắng mặt), bảo hiểm, thuế thu nhập cá nhân và lương thực lĩnh từng người.
3. Nhân sự, Giám đốc và Kế toán xem trước bảng lương để kiểm tra.
4. Nhân sự khóa bảng lương để chốt số liệu; từ lúc này nhân viên xem được phiếu lương của mình. Giám đốc duyệt bảng lương đã khóa. Hệ thống tự sinh bút toán lương vào sổ kế toán: chi phí lương, lương phải trả nhân viên, bảo hiểm phải nộp, thuế phải nộp và các khoản khấu trừ lương.
5. Sau khi chi trả lương thực tế cho nhân viên, Kế toán đánh dấu kỳ lương đã trả để hoàn tất quy trình. Việc chi trả tiền lương thực tế thực hiện ngoài hệ thống; kế toán ghi nhận bằng bút toán tay (giảm khoản lương phải trả nhân viên, giảm tiền mặt hoặc tiền gửi ngân hàng).

### 8.4. Luồng sản xuất trọn vẹn

1. Nhân viên sản xuất lập lệnh sản xuất cho một thành phẩm (từ đơn bán hoặc lập trực tiếp), gắn định mức nguyên vật liệu, quy trình sản xuất (nếu có) và kho.
2. Xác nhận lệnh rồi bắt đầu sản xuất. Hệ thống kiểm tra nguyên liệu theo định mức: đủ thì bắt đầu và tạo các công đoạn; thiếu thì tự tạo yêu cầu mua gợi ý cho phần thiếu và dừng lại.
3. Trong quá trình sản xuất, ghi nhận tiến độ từng đợt (đợt này làm được bao nhiêu thành phẩm, hỏng bao nhiêu phế phẩm), cập nhật trạng thái từng công đoạn và thời gian thực tế.
4. Hoàn thành sản xuất: nhập số thành phẩm và phế phẩm của đợt cuối. Hệ thống xuất kho nguyên liệu và nhập kho thành phẩm cho phần còn lại (không tính trùng các đợt đã ghi tiến độ), ghi nhật ký sản xuất và đóng các công đoạn còn dở.
5. Thành phẩm nhập kho sẵn sàng để bán và giao hàng theo luồng bán hàng (mục 8.1).

---

## 9. Lưu ý về phạm vi của bản hiện tại

Những điểm sau mô tả đúng giới hạn của hệ thống như đã xây dựng; khi vận hành thực tế cần lưu ý:

**Về tính toán và số liệu**

- Giá vốn hàng xuất kho tính theo phương pháp bình quân gia quyền; bản hiện tại chưa áp dụng phương pháp nhập trước xuất trước cho việc tính giá vốn.
- Báo cáo chỉ xuất ra tập tin CSV; chưa xuất được tập tin PDF hay Excel và chưa gửi báo cáo định kỳ tự động.
- Tỷ lệ bảo hiểm và biểu thuế thu nhập cá nhân trong bảng lương đã cập nhật theo quy định từ 1/7/2026 (Luật Thuế thu nhập cá nhân 109/2025/QH15; mức trần bảo hiểm thất nghiệp theo lương tối thiểu vùng I — xem mục 6.5). Khi luật thay đổi hoặc công ty ở vùng khác thì cần rà soát lại các mức này với kế toán hoặc chuyên gia thuế.
- Giá thành sản phẩm hiện chỉ tính theo chi phí nguyên vật liệu; chi phí nhân công và chi phí trung tâm sản xuất chưa đưa vào giá thành.
- Số tồn đầu kỳ và tồn cuối kỳ trong báo cáo xuất nhập tồn được tính đơn giản từ số liệu tổng hợp theo ngày (xem mục 7.5).

**Về phân quyền hiện tại**

- Việc lập yêu cầu mua, đơn mua, yêu cầu báo giá, quản lý nhà cung cấp và lập phiếu nhận hàng giao cho vai trò Nhân viên mua hàng; việc tạo và thực hiện lệnh sản xuất, cập nhật công đoạn và ghi nhận tiến độ giao cho vai trò Nhân viên sản xuất. Việc duyệt đơn mua, yêu cầu mua trên 50 triệu đồng, phiếu nhận hàng và đơn bán vượt hạn mức vẫn do Giám đốc thực hiện; việc quản lý định mức nguyên vật liệu, trung tâm sản xuất và quy trình sản xuất vẫn chỉ giao cho vai trò Quản trị hệ thống (Nhân viên sản xuất chỉ xem được các nội dung này).
- Quản lý nhà cung cấp ngoài Quản trị hệ thống còn giao cho Kế toán và Nhân viên mua hàng; Thủ kho chỉ xem được nhà cung cấp.

**Về tính năng chưa có trong bản này**

- Chưa có màn hình sửa nội dung quyền của từng vai trò và chưa có màn hình xem nhật ký thay đổi.
- Chưa có màn hình quản lý tài khoản ngân hàng và chưa đối chiếu sao kê ngân hàng.
- Hóa đơn mua lập từ phiếu nhận được hệ thống tự kết chuyển từ tài khoản hàng về chưa có hóa đơn (335) sang phải trả nhà cung cấp (331) khi ghi sổ; nhưng hóa đơn mua nhập tay cho hàng đã nhận rồi thì kế toán vẫn cần đối chiếu tay để không ghi nhận trùng giá trị hàng hóa (xem mục 1.4).
- Chưa tích hợp hóa đơn điện tử và chưa lập tờ khai thuế; hệ thống chỉ tính thuế suất trên từng dòng hóa đơn.
- Chưa có tính năng hoạch định nhu cầu sản xuất và mua hàng tự động; hệ thống chỉ gợi ý mua khi thiếu tồn kho hoặc thiếu nguyên liệu.
- Chưa theo dõi số serial từng chiếc; chưa cảnh báo theo hạn sử dụng của lô hàng.
- Chấm công chỉ nhập bằng tay, chưa nhập tự động từ máy chấm công; chưa có lịch nghỉ lễ trong hệ thống nên ngày lễ nhân sự cần tạo đơn nghỉ hoặc bản ghi nghỉ để lịch chấm công không báo thiếu chấm công.
- Hoàn thành sản xuất và ghi nhận tiến độ chưa sinh bút toán kế toán (chỉ đổi tồn kho); nguyên liệu xuất kho tính theo định mức, chưa nhập được số nguyên liệu thực tế đã tiêu hao.
- Bảng giá chỉ để tra cứu, chưa tự động áp giá vào báo giá hay đơn bán.
- Chưa có màn hình quản lý hợp đồng lao động, ca làm việc, chức danh, các khoản phụ cấp/khấu trừ lương và vị trí trong kho; các danh mục này đã có dữ liệu mẫu hoặc cấu trúc sẵn trong hệ thống.
- Hệ thống chưa giữ hàng (đặt trước tồn kho) khi xác nhận đơn bán; tồn kho chỉ trừ khi giao hàng thực tế được xác nhận.
- Hóa đơn và phiếu thu/chi đã ghi sổ không hủy trực tiếp được; sai sót sau ghi sổ xử lý bằng bút toán điều chỉnh.

---

*Tài liệu mô tả luồng nghiệp vụ của hệ thống ERP đang vận hành cho Công ty TNHH XT Mechanical, viết theo đúng các tính năng đã xây dựng và dữ liệu dùng thử hiện có.*
