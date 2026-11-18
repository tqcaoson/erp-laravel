<?php

namespace App\Core\Contracts;

/**
 * Hợp đồng kho hàng mà các module khác dùng để đọc/ghi tồn.
 *
 * Các module KHÔNG import trực tiếp model của Inventory để sửa dữ liệu,
 * mà gọi qua interface này (đúng quy tắc phụ thuộc mục 3 của thiết kế).
 * Implementation: App\Modules\Inventory\Services\StockService.
 */
interface StockServiceInterface
{
    /** Tồn khả dụng (tổng tồn trừ đi lượng đã giữ) của 1 sản phẩm tại 1 kho. */
    public function availableQty(int $productId, int $warehouseId): float;

    /** Nhập kho: ghi stock_moves + cập nhật stock_quants trong 1 transaction. Trả về id của stock_move. */
    public function receive(int $productId, int $warehouseId, ?int $locationId, float $qty, float $unitCost, string $sourceType, int $sourceId, ?int $lotId = null): int;

    /**
     * Xuất kho: khóa dòng stock_quants (FOR UPDATE), kiểm tra tồn khả dụng
     * rồi mới ghi move. Thiếu tồn sẽ ném \DomainException.
     * Trả về id của stock_move.
     */
    public function issue(int $productId, int $warehouseId, ?int $locationId, float $qty, string $sourceType, int $sourceId, ?int $lotId = null): int;

    /** Chuyển kho: xuất ở kho nguồn + nhập ở kho đích trong 1 transaction. */
    public function transfer(int $productId, int $fromWarehouseId, int $toWarehouseId, ?int $fromLocationId, ?int $toLocationId, float $qty, string $sourceType, int $sourceId): int;

    /** Giá vốn bình quân hiện tại của sản phẩm (để tính giá vốn xuất kho). */
    public function averageCost(int $productId): float;
}
