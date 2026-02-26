using Microsoft.Extensions.Logging;
using RestaurantDesktopApp.Core.Models;
using RestaurantDesktopApp.Core.Persistence;

namespace RestaurantDesktopApp.Core.Services;

public interface INotificationService
{
    Task NotifyAsync(string title, string body, bool playSound, string? soundPath, CancellationToken ct);
}

public interface IPrintDispatcher
{
    Task PrintOrderAsync(OrderItem order, int copies, CancellationToken ct);
    Task PrintBookingAsync(BookingItem booking, int copies, CancellationToken ct);
}

public sealed class SyncEngine
{
    private readonly IOrderSource _orderSource;
    private readonly IBookingSource _bookingSource;
    private readonly SyncStateRepository _state;
    private readonly INotificationService _notifications;
    private readonly IPrintDispatcher _printing;
    private readonly ILogger _logger;

    public SyncEngine(IOrderSource orderSource, IBookingSource bookingSource, SyncStateRepository state, INotificationService notifications, IPrintDispatcher printing, ILogger logger)
    {
        _orderSource = orderSource;
        _bookingSource = bookingSource;
        _state = state;
        _notifications = notifications;
        _printing = printing;
        _logger = logger;
    }

    public async Task<PollResult> PollAsync(AppSettings settings, DateTimeOffset after, CancellationToken ct)
    {
        var result = new PollResult();
        var orders = await RetryHttpExecutor.ExecuteAsync(() => _orderSource.GetRecentOrdersAsync(after, ct), _logger);
        foreach (var order in orders.OrderBy(x => x.CreatedAt))
        {
            var existing = await _state.GetAsync(order.Id, ItemType.Order, ct);
            if (existing is not null) continue;

            await ProcessOrderAsync(order, settings, ct);
            result.NewOrders++;
        }

        var bookings = await RetryHttpExecutor.ExecuteAsync(() => _bookingSource.GetRecentBookingsAsync(after, ct), _logger);
        foreach (var booking in bookings.OrderBy(x => x.CreatedAt))
        {
            var existing = await _state.GetAsync(booking.Id, ItemType.Booking, ct);
            if (existing is not null) continue;

            await ProcessBookingAsync(booking, settings, ct);
            result.NewBookings++;
        }

        return result;
    }

    private async Task ProcessOrderAsync(OrderItem order, AppSettings settings, CancellationToken ct)
    {
        var record = new SyncRecord
        {
            Id = order.Id,
            Type = ItemType.Order,
            CreatedAt = order.CreatedAt,
            LastUpdatedAt = DateTimeOffset.UtcNow,
            Status = SyncStatus.New,
            Printed = false
        };

        await _notifications.NotifyAsync("New order", $"Order #{order.Number} from {order.CustomerName}", settings.EnableSound, settings.SoundPath, ct);

        if (settings.AutoPrintOrders)
        {
            try
            {
                await _printing.PrintOrderAsync(order, settings.OrderCopies, ct);
                record.Printed = true;
                record.Status = SyncStatus.Printed;
            }
            catch (Exception ex)
            {
                record.Status = SyncStatus.PrintFailed;
                record.LastError = ex.Message;
                _logger.LogError(ex, "Order print failed for {OrderId}", order.Id);
            }
        }

        await _state.UpsertAsync(record, ct);
    }

    private async Task ProcessBookingAsync(BookingItem booking, AppSettings settings, CancellationToken ct)
    {
        var record = new SyncRecord
        {
            Id = booking.Id,
            Type = ItemType.Booking,
            CreatedAt = booking.CreatedAt,
            LastUpdatedAt = DateTimeOffset.UtcNow,
            Status = SyncStatus.New,
            Printed = false
        };

        await _notifications.NotifyAsync("New booking", $"{booking.Name} - {booking.PartySize} guests", settings.EnableSound, settings.SoundPath, ct);

        if (settings.AutoPrintBookings)
        {
            try
            {
                await _printing.PrintBookingAsync(booking, settings.BookingCopies, ct);
                record.Printed = true;
                record.Status = SyncStatus.Printed;
            }
            catch (Exception ex)
            {
                record.Status = SyncStatus.PrintFailed;
                record.LastError = ex.Message;
                _logger.LogError(ex, "Booking print failed for {BookingId}", booking.Id);
            }
        }

        await _state.UpsertAsync(record, ct);
    }
}

public sealed class PollResult
{
    public int NewOrders { get; set; }
    public int NewBookings { get; set; }
}
