<?php

declare(strict_types=1);

namespace SwiftPrint;

defined('ABSPATH') || exit;

final class Pricing_Engine {
    public function __construct(private Repository $repository) {}

    public function quote(int $productId, array $selection): array {
        $schema = $this->repository->get_set_by_product($productId);
        if (! $schema) {
            return ['error' => 'No SwiftPrint schema configured.'];
        }

        $quantity = max(1, (int) ($selection['quantity'] ?? 1));
        [$area, $frame] = $this->resolve_geometry($schema, $selection);
        $base = $this->compute_base_price($schema, $selection, $quantity, $area);

        $lines = [['code' => 'base', 'label' => 'Base price', 'amount' => $base]];
        $options = $this->compute_options($schema, $selection, $quantity, $base, $area, $frame, $lines);
        $subtotal = $base + $options;
        $turnaround = $this->compute_turnaround($schema, $selection, $subtotal, $base, $quantity);
        $subtotal += $turnaround;
        if ($turnaround > 0) {
            $lines[] = ['code' => 'turnaround', 'label' => 'Turnaround', 'amount' => $turnaround];
        }

        $discount = $this->compute_discount($schema, $subtotal);
        if ($discount > 0) {
            $lines[] = ['code' => 'discount', 'label' => 'Discount', 'amount' => -$discount];
        }

        $total = max(0, $subtotal - $discount);
        $weight = $this->compute_weight($schema, $selection, $quantity, $area);

        return [
            'schema' => $schema,
            'quantity' => $quantity,
            'area' => $area,
            'frame_length' => $frame,
            'base_price' => round($base, 2),
            'total' => round($total, 2),
            'weight' => round($weight, 4),
            'breakdown' => array_map(static fn ($line) => [
                'code' => $line['code'],
                'label' => $line['label'],
                'amount' => round((float) $line['amount'], 2),
            ], $lines),
        ];
    }

    private function resolve_geometry(array $schema, array $selection): array {
        $w = 0.0; $h = 0.0;
        if (! empty($selection['custom_size']['enabled'])) {
            $w = (float) ($selection['custom_size']['width'] ?? 0);
            $h = (float) ($selection['custom_size']['height'] ?? 0);
        } else {
            $sizeId = (string) ($selection['size_id'] ?? '');
            foreach ($schema['standard_sizes'] as $size) {
                if (($size['size_id'] ?? '') === $sizeId) {
                    $w = (float) ($size['width'] ?? 0);
                    $h = (float) ($size['height'] ?? 0);
                    break;
                }
            }
        }

        $area = $w * $h;
        $frame = ($w * 2) + ($h * 2);
        return [$area, $frame];
    }

    private function compute_base_price(array $schema, array $selection, int $qty, float $area): float {
        if (! empty($selection['custom_size']['enabled'])) {
            $ranges = $schema['custom_size']['area_ranges'] ?? [];
            foreach ($ranges as $range) {
                if ($area >= (float) ($range['min_area'] ?? 0) && $area <= (float) ($range['max_area'] ?? INF)) {
                    return (float) $range['price_per_sq_unit'] * $area * $qty;
                }
            }
            return 0;
        }

        $sizeId = (string) ($selection['size_id'] ?? '');
        $mode = (string) ($selection['print_mode_key'] ?? 'SIMPLE_S1');
        $rows = array_values(array_filter($schema['pricing_rows'], static fn ($r) => $r['size_id'] === $sizeId && $r['print_mode_key'] === $mode));
        if (empty($rows)) {
            return 0;
        }
        usort($rows, static fn ($a, $b) => $a['quantity_break'] <=> $b['quantity_break']);

        $pricingMode = $schema['pricing_mode'] ?? 'LOOKUP';
        $lower = $rows[0];
        $upper = $rows[count($rows) - 1];
        foreach ($rows as $row) {
            if ($row['quantity_break'] <= $qty) {
                $lower = $row;
            }
            if ($row['quantity_break'] >= $qty) {
                $upper = $row;
                break;
            }
        }

        if ($pricingMode === 'LOOKUP') {
            return (float) $lower['total_price'];
        }

        if ($pricingMode === 'UP') {
            $unit = (float) $lower['total_price'] / max(1, (int) $lower['quantity_break']);
            return $unit * $qty;
        }

        if ((int) $lower['quantity_break'] === (int) $upper['quantity_break']) {
            return (float) $lower['total_price'];
        }

        $x1 = (int) $lower['quantity_break'];
        $x2 = (int) $upper['quantity_break'];
        $y1 = (float) $lower['total_price'];
        $y2 = (float) $upper['total_price'];

        if ($pricingMode === 'LUPI') {
            $y1 = $y1 / $x1;
            $y2 = $y2 / $x2;
            $unit = $y1 + ($qty - $x1) * (($y2 - $y1) / ($x2 - $x1));
            return $unit * $qty;
        }

        return $y1 + ($qty - $x1) * (($y2 - $y1) / ($x2 - $x1));
    }

    private function compute_options(array $schema, array $selection, int $qty, float $base, float $area, float $frame, array &$lines): float {
        $sum = 0.0;
        $selectedItems = $selection['selected_option_items'] ?? [];

        foreach ($schema['option_groups'] as $group) {
            foreach (($group['items'] ?? []) as $item) {
                if (! in_array($item['id'] ?? '', $selectedItems, true)) {
                    continue;
                }
                $cost = $this->resolve_option_cost($item, $selection, $qty, $area);
                $chargedAs = (string) ($item['charged_as'] ?? 'FLAT');
                $price = match ($chargedAs) {
                    'PER_ITEM', 'UNIT_PER_AREA_RANGE' => $cost * $qty,
                    'PERCENT' => $base * ($cost / 100),
                    'FLAT_PLUS_PERCENT' => $cost + ($base * ((float) ($item['percent'] ?? $cost) / 100)),
                    'SQUARE_UNIT', 'SQUARE_UNIT_PER_AREA_RANGE' => $cost * $area * $qty,
                    'LINEAR_UNIT' => $cost * $frame * $qty,
                    default => $cost,
                };
                $sum += $price;
                $lines[] = [
                    'code' => 'option_' . sanitize_key((string) ($item['id'] ?? 'option')),
                    'label' => (string) ($item['name'] ?? 'Option'),
                    'amount' => $price,
                ];
            }
        }

        return $sum;
    }

    private function resolve_option_cost(array $item, array $selection, int $qty, float $area): float {
        $sizeId = (string) ($selection['size_id'] ?? '');
        foreach (($item['standard_size_ranges'] ?? []) as $r) {
            if (($r['size_id'] ?? '') === $sizeId) {
                return (float) $r['cost'];
            }
        }
        foreach (($item['custom_size_area_ranges'] ?? []) as $r) {
            if ($area >= (float) ($r['min_area'] ?? 0) && $area <= (float) ($r['max_area'] ?? INF)) {
                return (float) $r['cost'];
            }
        }
        foreach (($item['quantity_ranges'] ?? []) as $r) {
            if ($qty >= (int) ($r['from_qty'] ?? 0) && $qty <= (int) ($r['to_qty'] ?? PHP_INT_MAX)) {
                return (float) $r['cost'];
            }
        }
        return (float) ($item['default_cost'] ?? 0);
    }

    private function compute_turnaround(array $schema, array $selection, float $subtotal, float $base, int $qty): float {
        $id = (string) ($selection['turnaround_id'] ?? '');
        foreach ($schema['turnarounds'] as $turn) {
            if ((string) ($turn['id'] ?? '') !== $id) {
                continue;
            }
            if (! empty($turn['min_qty']) && $qty < (int) $turn['min_qty']) {
                return 0;
            }
            if (! empty($turn['max_qty']) && $qty > (int) $turn['max_qty']) {
                return 0;
            }
            if (! empty($turn['cutoff_time']) && ! empty($turn['same_day'])) {
                $tz = wp_timezone();
                $now = new \DateTimeImmutable('now', $tz);
                $cut = \DateTimeImmutable::createFromFormat('H:i', (string) $turn['cutoff_time'], $tz);
                if ($cut && $now > $cut) {
                    return 0;
                }
            }
            return match ($turn['cost_type'] ?? 'FLAT') {
                'PERCENT_BASE' => $base * ((float) ($turn['cost_value'] ?? 0) / 100),
                'PERCENT_SUBTOTAL' => $subtotal * ((float) ($turn['cost_value'] ?? 0) / 100),
                default => (float) ($turn['cost_value'] ?? 0),
            };
        }
        return 0;
    }

    private function compute_discount(array $schema, float $subtotal): float {
        if (empty($schema['discount_enabled'])) {
            return 0;
        }
        $value = (float) ($schema['discount_value'] ?? 0);
        if (($schema['discount_type'] ?? 'FIXED') === 'PERCENT') {
            return $subtotal * ($value / 100);
        }
        return $value;
    }

    private function compute_weight(array $schema, array $selection, int $qty, float $area): float {
        $weight = (float) ($schema['weight_value'] ?? 0);
        $total = ($schema['weight_per'] ?? 'UNIT') === 'AREA' ? $weight * $area * $qty : $weight * $qty;

        $selectedItems = $selection['selected_option_items'] ?? [];
        foreach ($schema['option_groups'] as $group) {
            foreach (($group['items'] ?? []) as $item) {
                if (! in_array($item['id'] ?? '', $selectedItems, true)) {
                    continue;
                }
                $itemWeight = (float) ($item['item_weight'] ?? 0);
                $chargedAs = $item['charged_as'] ?? 'FLAT';
                if (in_array($chargedAs, ['SQUARE_UNIT', 'SQUARE_UNIT_PER_AREA_RANGE'], true)) {
                    $total += $itemWeight * $area * $qty;
                } else {
                    $total += $itemWeight;
                }
            }
        }

        return $total;
    }
}
