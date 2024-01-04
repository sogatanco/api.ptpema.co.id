# Vendor API

## Authentication
`https://api.ptpema.co.id/api/auth2`

### Resend Email Verification
Request
```http
GET /resend/[id_user]
```
Parameter
- `id_user` : `[integer]` - userId  

Success Response
```
{
    "success": true,
    "message": "Email Verification sent succesfully",
    "data": []
}
```
Error Response
```
{
    "success": false,
    "message": "Failed to send",
    "data": []
}
```

### Register
Request
```http
POST /register
```
Parameter
- `email` : `[email]` 
- `password` : `[text]` 
- `bentukUsaha` : `enum[PT,CV,Firma,PO]` 
- `namaPerusahaan` : `[text]` 
- `tipePenyedia` : `enum[Barang,Jasa,Barang dan Jasa]` 


Success Response
```
{
    "success": true,
    "message": "Register Success",
    "data": [user data]
}
```
Error Response
```
{
    "success": false,
    "message": "Failed To Register",
    "data": []
}
```

```
{
    "success": false,
    "message": "Email already registered",
    "data": []
}
```
### Login
Request
```http
POST /login
```
Parameter
- `email` : `[email]` 
- `password` : `[text]` 

Success Response
```
{
    "status": true,
    "message": "Login success.",
    "auth": {
        "user": {
            "id": 16,
            "email": "wahyudin@ptpema.co.id",
            "roles": [
                "Vendor"
            ],
            "created_at": "2023-12-13T03:38:19.000000Z",
            "updated_at": "2023-12-13T03:38:40.000000Z",
            "is_email_verified": 1,
            "company_id": 30
        },
        "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJodHRwOi8vYXBpLnB0cGVtYS5jby5pZC9hcGkvYXV0aDIvbG9naW4iLCJpYXQiOjE3MDI0Mzg4ODgsImV4cCI6MTcwMjQ0MjQ4OCwibmJmIjoxNzAyNDM4ODg4LCJqdGkiOiIyTTUyYzFOclFTSk1iQjg0Iiwic3ViIjoiMTYiLCJwcnYiOiI0MDdlNzcyOTMzY2Q2MDVjYTc2MmZhM2E4MjRmOWIwZjY3ZmRiMDI5IiwiaWQiOjE2LCJlbWFpbCI6IndhaHl1ZGluQHB0cGVtYS5jby5pZCIsInJvbGVzIjpbIlZlbmRvciJdfQ.1Xr78xmPv-tBhkj1rstAoiAHVBLouhGrxK7FgGVe6hk"
    }
}
```
Error Response
```
{
    "status": false,
    "message": "Email or password is invalid."
}
```
```
{
    "status": false,
    "message": "User not verified, Please Check your email for verification"
}
```

## Directors
`https://api.ptpema.co.id/vapi`
### Add Jajaran Direksi
Request
```http
POST /jajaran/store
```
Parameter
- `nama` : `[text]`
- `jabatan` : `[text]`
- `no_npwp_direksi` : `[text]`

Success Response
```
{
    "status": true,
    "message": "Jajaran Direksi Inserted !",
    "data":[]
}
```
Error Response
```
{
    "status": true,
    "message": "Jajaran Direksi Failed to insert!",
    "data":[]
}
```
### Edit Jajaran Direksi
Request
```http
POST /jajaran/edit/[id]
```
Parameter
- `id` : `[number]` parameter in link
- `nama` : `[text]`
- `jabatan` : `[text]`
- `no_npwp_direksi` : `[text]`

Success Response
```
{
    "success": true,
    "message": "Jajaran Direksi Updated Succesfully",
    "data": []
}
```
Error Response
```
{
    "success": false,
    "message": "Failed to update!",
    "data": []
}
```

### My Directors
Request
```
GET /jajaran/my
```
Success Response
```
{
    "success": true,
    "message": "My Directors",
    "data": 
}
```
### Delete Direksi
Request
```
POST /jajaran/delete/[id]
```
Parameter
- `id` : `[number]` parameter in link
Success Response
```
{
    "success": true,
    "message": "Deleted Succesfully",
    "data": []
}
```
Error Response
```
{
    "success": false,
    "message": "Failed to delete,
    "data": []
}
```

## Akta Perusahaan
`https://api.ptpema.co.id/vapi/akta`
### Tambah Akta baru
Request
```
POST /store
```
Parameter
- `file` : `[text]` .pdf in base64 format
- `no_akta` : `[text]`
- `nama_notaris` : `[text]`
- `tgl_terbit` : `[date]` must in format YYYY-MM-DD ex : 2023-11-02
Success Response
```
{
    "success": true,
    "message": "New Akta Inserted",
    "data": []
}
```
Error Response
```
{
    "success": false,
    "message": "Failed to add akta,
    "data": []
}
```

### View Akta
Request
```
GET /view
```
Parameter

Success Response
```
{
"success": true,
    "message": "New Akta Inserted",
    "data": {
        "id_akta": 4,
        "id_perusahaan": 30,
        "no_akta": "gdagsdasdg",
        "tgl_terbit": "2023-11-02",
        "nama_notaris": "whyudin",
        "file_akta": "akta/1702527567.pdf",
        "created_at": "2023-12-14T04:19:27.000000Z",
        "updated_at": "2023-12-14T04:19:27.000000Z",
        "file_base64": "JVBERi0xLjMKJcTl8uXrp/"
    }
}
```

### Delete Akta
Request
```
GET /delete/[id]
```
Parameter
- `id`:`[number]` in link

Success Response
```
{
    "success": true,
    "message": "Deleted Succesfully",
    "data": []
}
```
Error Response
```
{
    "success": false,
    "message": "Not Permitted",
    "data": []
}
```