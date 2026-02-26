namespace RestaurantDesktopApp.Core.Models;

public enum ItemType { Order, Booking }
public enum SyncStatus { New, Acknowledged, PrintFailed, Printed }

public sealed class OrderItem
{
    public required long Id { get; init; }
    public required DateTimeOffset CreatedAt { get; init; }
    public required string Number { get; init; }
    public required string Status { get; init; }
    public required string CustomerName { get; init; }
    public string? Phone { get; init; }
    public string? Notes { get; init; }
    public required string Currency { get; init; }
    public decimal Total { get; init; }
    public List<OrderLine> Lines { get; init; } = [];
    public string? AdminUrl { get; init; }
}

public sealed class OrderLine
{
    public required string Name { get; init; }
    public required decimal Quantity { get; init; }
    public required decimal Total { get; init; }
    public string? Meta { get; init; }
}

public sealed class BookingItem
{
    public required long Id { get; init; }
    public required DateTimeOffset CreatedAt { get; init; }
    public required DateTimeOffset BookingTime { get; init; }
    public required int PartySize { get; init; }
    public required string Name { get; init; }
    public string? Phone { get; init; }
    public string? Email { get; init; }
    public string? Notes { get; init; }
    public string Status { get; init; } = "new";
    public string? AdminUrl { get; init; }
}

public sealed class AppSettings
{
    public string BaseUrl { get; set; } = string.Empty;
    public string AuthMode { get; set; } = "WooCommerceKeys";
    public string UsernameOrConsumerKey { get; set; } = string.Empty;
    public string PasswordOrConsumerSecret { get; set; } = string.Empty;
    public int PollSeconds { get; set; } = 8;
    public string PrinterName { get; set; } = string.Empty;
    public string PaperWidth { get; set; } = "80mm";
    public int OrderCopies { get; set; } = 2;
    public int BookingCopies { get; set; } = 1;
    public bool EnableSound { get; set; } = true;
    public string SoundPath { get; set; } = "Assets/default-notification.wav";
    public bool AutoPrintOrders { get; set; } = true;
    public bool AutoPrintBookings { get; set; } = true;
    public string RestaurantHeader { get; set; } = "Restaurant";
    public string PrintingMode { get; set; } = "WindowsDriver";
    public string? EpsonEposPrinterIp { get; set; }
    public string? EpsonEposEndpointPath { get; set; }
}

public sealed class SyncRecord
{
    public long Id { get; init; }
    public required ItemType Type { get; init; }
    public required DateTimeOffset CreatedAt { get; init; }
    public SyncStatus Status { get; set; }
    public bool Printed { get; set; }
    public string? LastError { get; set; }
    public DateTimeOffset LastUpdatedAt { get; set; }
}
