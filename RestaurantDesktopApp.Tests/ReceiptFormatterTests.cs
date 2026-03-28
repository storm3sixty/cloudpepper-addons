using RestaurantDesktopApp.Core.Models;
using RestaurantDesktopApp.Printing.Formatting;

namespace RestaurantDesktopApp.Tests;

public class ReceiptFormatterTests
{
    [Fact]
    public void FormatOrder_ContainsImportantFields()
    {
        var formatter = new ReceiptFormatter();
        var output = formatter.FormatOrder(new OrderItem
        {
            Id = 7,
            Number = "7",
            Status = "processing",
            CreatedAt = new DateTimeOffset(2025, 01, 01, 12, 0, 0, TimeSpan.Zero),
            CustomerName = "Jordan",
            Currency = "USD",
            Total = 30,
            Lines = [new OrderLine { Name = "Pizza", Quantity = 1, Total = 30 }]
        }, new AppSettings { RestaurantHeader = "Cloud Pepper" });

        Assert.Contains("Cloud Pepper", output);
        Assert.Contains("ORDER #7", output);
        Assert.Contains("TOTAL: 30.00 USD", output);
    }
}
