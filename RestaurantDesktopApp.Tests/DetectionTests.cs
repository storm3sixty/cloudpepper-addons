using Microsoft.Extensions.Logging.Abstractions;
using RestaurantDesktopApp.Core.Models;
using RestaurantDesktopApp.Core.Persistence;
using RestaurantDesktopApp.Core.Services;

namespace RestaurantDesktopApp.Tests;

public class DetectionTests
{
    [Fact]
    public async Task SyncEngine_DoesNotDoubleProcessExistingIds()
    {
        var temp = Path.GetTempFileName();
        var state = new SyncStateRepository(temp);
        await state.InitializeAsync();
        await state.UpsertAsync(new SyncRecord
        {
            Id = 1,
            Type = ItemType.Order,
            CreatedAt = DateTimeOffset.UtcNow,
            LastUpdatedAt = DateTimeOffset.UtcNow,
            Status = SyncStatus.Printed,
            Printed = true
        });

        var engine = new SyncEngine(
            new FakeOrderSource(),
            new FakeBookingSource(),
            state,
            new FakeNotifier(),
            new FakePrinter(),
            NullLogger.Instance);

        var result = await engine.PollAsync(new AppSettings(), DateTimeOffset.UtcNow.AddHours(-1), CancellationToken.None);
        Assert.Equal(0, result.NewOrders);
    }

    private sealed class FakeOrderSource : IOrderSource
    {
        public Task<IReadOnlyList<OrderItem>> GetRecentOrdersAsync(DateTimeOffset after, CancellationToken ct)
            => Task.FromResult<IReadOnlyList<OrderItem>>([new OrderItem { Id = 1, CreatedAt = DateTimeOffset.UtcNow, Number = "1", Status = "processing", CustomerName = "A", Currency = "USD" }]);
    }

    private sealed class FakeBookingSource : IBookingSource
    {
        public Task<IReadOnlyList<BookingItem>> GetRecentBookingsAsync(DateTimeOffset after, CancellationToken ct)
            => Task.FromResult<IReadOnlyList<BookingItem>>([]);
    }

    private sealed class FakeNotifier : INotificationService
    {
        public Task NotifyAsync(string title, string body, bool playSound, string? soundPath, CancellationToken ct) => Task.CompletedTask;
    }

    private sealed class FakePrinter : IPrintDispatcher
    {
        public Task PrintOrderAsync(OrderItem order, int copies, CancellationToken ct) => Task.CompletedTask;
        public Task PrintBookingAsync(BookingItem booking, int copies, CancellationToken ct) => Task.CompletedTask;
    }
}
