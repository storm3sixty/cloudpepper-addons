using System.Security.Cryptography;
using System.Text;

namespace RestaurantDesktopApp.Core.Utils;

public interface ISecretStore
{
    string Protect(string value);
    string Unprotect(string value);
}

public sealed class DpapiSecretStore : ISecretStore
{
    public string Protect(string value)
    {
        if (string.IsNullOrWhiteSpace(value)) return value;
        var raw = Encoding.UTF8.GetBytes(value);
        return Convert.ToBase64String(ProtectedData.Protect(raw, null, DataProtectionScope.CurrentUser));
    }

    public string Unprotect(string value)
    {
        if (string.IsNullOrWhiteSpace(value)) return value;
        var raw = Convert.FromBase64String(value);
        return Encoding.UTF8.GetString(ProtectedData.Unprotect(raw, null, DataProtectionScope.CurrentUser));
    }
}
