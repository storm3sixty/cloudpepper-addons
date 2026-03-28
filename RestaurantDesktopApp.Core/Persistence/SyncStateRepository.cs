using Microsoft.Data.Sqlite;
using RestaurantDesktopApp.Core.Models;

namespace RestaurantDesktopApp.Core.Persistence;

public sealed class SyncStateRepository
{
    private readonly string _connectionString;

    public SyncStateRepository(string dbPath)
    {
        Directory.CreateDirectory(Path.GetDirectoryName(dbPath)!);
        _connectionString = $"Data Source={dbPath}";
    }

    public async Task InitializeAsync(CancellationToken ct = default)
    {
        await using var conn = new SqliteConnection(_connectionString);
        await conn.OpenAsync(ct);
        var cmd = conn.CreateCommand();
        cmd.CommandText = @"
CREATE TABLE IF NOT EXISTS sync_items (
  id INTEGER NOT NULL,
  type TEXT NOT NULL,
  created_at TEXT NOT NULL,
  status TEXT NOT NULL,
  printed INTEGER NOT NULL,
  last_error TEXT NULL,
  last_updated_at TEXT NOT NULL,
  PRIMARY KEY(id, type)
);
";
        await cmd.ExecuteNonQueryAsync(ct);
    }

    public async Task<SyncRecord?> GetAsync(long id, ItemType type, CancellationToken ct = default)
    {
        await using var conn = new SqliteConnection(_connectionString);
        await conn.OpenAsync(ct);
        var cmd = conn.CreateCommand();
        cmd.CommandText = "SELECT id, type, created_at, status, printed, last_error, last_updated_at FROM sync_items WHERE id=$id AND type=$type";
        cmd.Parameters.AddWithValue("$id", id);
        cmd.Parameters.AddWithValue("$type", type.ToString());
        await using var reader = await cmd.ExecuteReaderAsync(ct);
        if (!await reader.ReadAsync(ct)) return null;
        return new SyncRecord
        {
            Id = reader.GetInt64(0),
            Type = Enum.Parse<ItemType>(reader.GetString(1)),
            CreatedAt = DateTimeOffset.Parse(reader.GetString(2)),
            Status = Enum.Parse<SyncStatus>(reader.GetString(3)),
            Printed = reader.GetInt32(4) == 1,
            LastError = reader.IsDBNull(5) ? null : reader.GetString(5),
            LastUpdatedAt = DateTimeOffset.Parse(reader.GetString(6))
        };
    }

    public async Task UpsertAsync(SyncRecord record, CancellationToken ct = default)
    {
        await using var conn = new SqliteConnection(_connectionString);
        await conn.OpenAsync(ct);
        var cmd = conn.CreateCommand();
        cmd.CommandText = @"
INSERT INTO sync_items(id, type, created_at, status, printed, last_error, last_updated_at)
VALUES($id, $type, $created_at, $status, $printed, $last_error, $last_updated_at)
ON CONFLICT(id, type)
DO UPDATE SET status=$status, printed=$printed, last_error=$last_error, last_updated_at=$last_updated_at;";
        cmd.Parameters.AddWithValue("$id", record.Id);
        cmd.Parameters.AddWithValue("$type", record.Type.ToString());
        cmd.Parameters.AddWithValue("$created_at", record.CreatedAt.ToString("O"));
        cmd.Parameters.AddWithValue("$status", record.Status.ToString());
        cmd.Parameters.AddWithValue("$printed", record.Printed ? 1 : 0);
        cmd.Parameters.AddWithValue("$last_error", (object?)record.LastError ?? DBNull.Value);
        cmd.Parameters.AddWithValue("$last_updated_at", record.LastUpdatedAt.ToString("O"));
        await cmd.ExecuteNonQueryAsync(ct);
    }
}
