```mermaid
flowchart TD
    User["ผู้ใช้ (User)"]
    Admin["ผู้ดูแลระบบ (Admin)"]
    WebApp["ระบบเว็บ TT (Web Application)"]
    UsersJSON["users.json"]
    StatsJSON["stats.json"]
    QuestsJSON["quests.json"]
    CatalogJSON["catalog.json"]
    Uploads["uploads/ (ไฟล์แนบ)"]

    User -- "เข้าสู่ระบบ / ส่งเควส / ดูข้อมูล" --> WebApp
    Admin -- "เข้าสู่ระบบ / ตรวจสอบ / อนุมัติ / แก้ไข Catalog" --> WebApp

    WebApp -- "อ่าน/เขียนข้อมูลผู้ใช้" --> UsersJSON
    WebApp -- "อ่าน/เขียนข้อมูลสถิติ/คะแนน" --> StatsJSON
    WebApp -- "อ่าน/เขียนข้อมูลเควส" --> QuestsJSON
    WebApp -- "อ่าน/เขียนข้อมูลคลังวิชา" --> CatalogJSON
    WebApp -- "อัปโหลด/ดาวน์โหลดไฟล์" --> Uploads

    UsersJSON -- "ข้อมูลผู้ใช้" --> WebApp
    StatsJSON -- "ข้อมูลคะแนน/สถิติ" --> WebApp
    QuestsJSON -- "ข้อมูลเควส" --> WebApp
    CatalogJSON -- "ข้อมูลคลังรายวิชา" --> WebApp
    Uploads -- "ไฟล์แนบเควส" --> WebApp