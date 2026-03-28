using System.Text.Json;
using RestaurantDesktopApp.Core.Services;

namespace RestaurantDesktopApp.Tests;

public class ParsingTests
{
    [Fact]
    public void WooOrderParser_MapsFields()
    {
        var json = """
        {
          "id": 123,
          "date_created_gmt": "2025-01-01T10:00:00",
          "number": "123",
          "status": "processing",
          "currency": "USD",
          "total": "42.50",
          "customer_note": "No onions",
          "billing": {"first_name":"Sam","last_name":"Lee","phone":"1234"},
          "line_items":[{"name":"Burger","quantity":2,"total":"21.25","meta_data":[]}],
          "_links":{"self":[{"href":"https://site/wp-json/wc/v3/orders/123"}]}
        }
        """;
        using var doc = JsonDocument.Parse(json);
        var order = WooCommerceOrderSource.MapOrder(doc.RootElement);

        Assert.Equal(123, order.Id);
        Assert.Equal("Sam Lee", order.CustomerName);
        Assert.Single(order.Lines);
        Assert.Equal(42.50m, order.Total);
    }

    [Fact]
    public void BookingParser_MapsFields()
    {
        var json = """
        {
          "id": 20,
          "created_at": "2025-01-01T09:00:00Z",
          "booking_time": "2025-01-01T19:00:00Z",
          "party_size": 4,
          "name": "Alex",
          "phone": "111",
          "status": "new"
        }
        """;
        using var doc = JsonDocument.Parse(json);
        var booking = GenericBookingSource.MapBooking(doc.RootElement);

        Assert.Equal(20, booking.Id);
        Assert.Equal(4, booking.PartySize);
        Assert.Equal("Alex", booking.Name);
    }
}
