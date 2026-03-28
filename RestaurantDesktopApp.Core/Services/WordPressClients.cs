using System.Net.Http.Headers;
using System.Text;
using System.Text.Json;
using Microsoft.Extensions.Logging;
using RestaurantDesktopApp.Core.Models;

namespace RestaurantDesktopApp.Core.Services;

public interface IOrderSource
{
    Task<IReadOnlyList<OrderItem>> GetRecentOrdersAsync(DateTimeOffset after, CancellationToken ct);
}

public interface IBookingSource
{
    Task<IReadOnlyList<BookingItem>> GetRecentBookingsAsync(DateTimeOffset after, CancellationToken ct);
}

public sealed class WooCommerceOrderSource : IOrderSource
{
    private readonly HttpClient _http;
    private readonly AppSettings _settings;

    public WooCommerceOrderSource(HttpClient http, AppSettings settings)
    {
        _http = http;
        _settings = settings;
    }

    public async Task<IReadOnlyList<OrderItem>> GetRecentOrdersAsync(DateTimeOffset after, CancellationToken ct)
    {
        var url = $"{_settings.BaseUrl.TrimEnd('/')}/wp-json/wc/v3/orders?status=processing,on-hold&after={Uri.EscapeDataString(after.ToString("O"))}&orderby=date&order=asc";
        using var req = new HttpRequestMessage(HttpMethod.Get, url);
        ApplyAuth(req);
        var response = await _http.SendAsync(req, ct);
        response.EnsureSuccessStatusCode();

        await using var stream = await response.Content.ReadAsStreamAsync(ct);
        using var doc = await JsonDocument.ParseAsync(stream, cancellationToken: ct);
        return doc.RootElement.EnumerateArray().Select(MapOrder).ToList();
    }

    private void ApplyAuth(HttpRequestMessage req)
    {
        if (_settings.AuthMode == "WooCommerceKeys")
        {
            var token = Convert.ToBase64String(Encoding.UTF8.GetBytes($"{_settings.UsernameOrConsumerKey}:{_settings.PasswordOrConsumerSecret}"));
            req.Headers.Authorization = new AuthenticationHeaderValue("Basic", token);
        }
        else
        {
            var token = Convert.ToBase64String(Encoding.UTF8.GetBytes($"{_settings.UsernameOrConsumerKey}:{_settings.PasswordOrConsumerSecret}"));
            req.Headers.Authorization = new AuthenticationHeaderValue("Basic", token);
        }
    }

    internal static OrderItem MapOrder(JsonElement o)
    {
        var lines = new List<OrderLine>();
        if (o.TryGetProperty("line_items", out var items))
        {
            foreach (var li in items.EnumerateArray())
            {
                lines.Add(new OrderLine
                {
                    Name = li.GetProperty("name").GetString() ?? "Item",
                    Quantity = li.GetProperty("quantity").GetDecimal(),
                    Total = decimal.Parse(li.GetProperty("total").GetString() ?? "0"),
                    Meta = li.TryGetProperty("meta_data", out var md) ? string.Join(", ", md.EnumerateArray().Select(x => $"{x.GetProperty("key").GetString()}: {x.GetProperty("value").ToString()}")) : null
                });
            }
        }

        var billing = o.GetProperty("billing");
        return new OrderItem
        {
            Id = o.GetProperty("id").GetInt64(),
            CreatedAt = DateTimeOffset.Parse(o.GetProperty("date_created_gmt").GetString() ?? DateTimeOffset.UtcNow.ToString("O")),
            Number = o.GetProperty("number").GetString() ?? o.GetProperty("id").GetInt64().ToString(),
            Status = o.GetProperty("status").GetString() ?? "processing",
            CustomerName = $"{billing.GetProperty("first_name").GetString()} {billing.GetProperty("last_name").GetString()}".Trim(),
            Phone = billing.TryGetProperty("phone", out var p) ? p.GetString() : null,
            Notes = o.TryGetProperty("customer_note", out var n) ? n.GetString() : null,
            Currency = o.GetProperty("currency").GetString() ?? "USD",
            Total = decimal.Parse(o.GetProperty("total").GetString() ?? "0"),
            Lines = lines,
            AdminUrl = o.TryGetProperty("id", out var idVal) ? $"{o.GetProperty("_links").GetProperty("self")[0].GetProperty("href").GetString()}" : null
        };
    }
}

public sealed class GenericBookingSource : IBookingSource
{
    private readonly HttpClient _http;
    private readonly AppSettings _settings;

    public GenericBookingSource(HttpClient http, AppSettings settings)
    {
        _http = http;
        _settings = settings;
    }

    public async Task<IReadOnlyList<BookingItem>> GetRecentBookingsAsync(DateTimeOffset after, CancellationToken ct)
    {
        var url = $"{_settings.BaseUrl.TrimEnd('/')}/wp-json/restaurant-sync/v1/bookings?after={Uri.EscapeDataString(after.ToString("O"))}";
        using var req = new HttpRequestMessage(HttpMethod.Get, url);
        var res = await _http.SendAsync(req, ct);
        res.EnsureSuccessStatusCode();
        await using var stream = await res.Content.ReadAsStreamAsync(ct);
        using var doc = await JsonDocument.ParseAsync(stream, cancellationToken: ct);
        return doc.RootElement.EnumerateArray().Select(MapBooking).ToList();
    }

    internal static BookingItem MapBooking(JsonElement b) => new()
    {
        Id = b.GetProperty("id").GetInt64(),
        CreatedAt = DateTimeOffset.Parse(b.GetProperty("created_at").GetString() ?? DateTimeOffset.UtcNow.ToString("O")),
        BookingTime = DateTimeOffset.Parse(b.GetProperty("booking_time").GetString() ?? DateTimeOffset.UtcNow.ToString("O")),
        PartySize = b.GetProperty("party_size").GetInt32(),
        Name = b.GetProperty("name").GetString() ?? "Guest",
        Phone = b.TryGetProperty("phone", out var p) ? p.GetString() : null,
        Email = b.TryGetProperty("email", out var e) ? e.GetString() : null,
        Notes = b.TryGetProperty("notes", out var n) ? n.GetString() : null,
        Status = b.TryGetProperty("status", out var s) ? s.GetString() ?? "new" : "new",
        AdminUrl = b.TryGetProperty("admin_url", out var a) ? a.GetString() : null
    };
}

public sealed class RetryHttpExecutor
{
    public static async Task<T> ExecuteAsync<T>(Func<Task<T>> action, ILogger logger, int maxAttempts = 4)
    {
        var delay = TimeSpan.FromMilliseconds(300);
        for (var attempt = 1; attempt <= maxAttempts; attempt++)
        {
            try { return await action(); }
            catch (Exception ex) when (attempt < maxAttempts)
            {
                logger.LogWarning(ex, "HTTP retry attempt {Attempt} failed", attempt);
                await Task.Delay(delay);
                delay = TimeSpan.FromMilliseconds(delay.TotalMilliseconds * 2);
            }
        }

        return await action();
    }
}
