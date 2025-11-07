# Dolphin Project

This project integrates a C++ application with a Python backend and a MySQL database.  
The Python scripts handle reading/writing to the test database (`testdb`) and manage user word selections and test results.  
The C++ program calls into Python scripts when needed.

---

## Requirements

Python 3.10+  
MySQL running locally (or accessible with correct credentials)  
C++ compiler (g++, clang++, or MSVC)  

### Python Dependencies

Installed via pip:

```
greenlet==3.2.4
PyMySQL==1.1.2
SQLAlchemy==2.0.43
typing_extensions==4.15.0
```

You can install them with:

```bash
pip install -r requirements.txt
```

### Database Setup

1. I used a test database I named `testdb` in MySQL.
2. Then I created the tables requiredd using:

```sql
SOURCE /MySQL/sql-create.sql;
```

This creates two tables:  
- `results` → stores test results, including timestamps and averages.  
- `input` → stores user emails and JSON arrays of self/concept words.  

Please see `MySQL/sql-create.sql` for full table schema.

## Running

1. Activate your virtual environment:
   ```bash
   source env/bin/activate
   ```

2. Build & run C++ program:
   ```bash
   ./make
   ./dolphin example@gmail.com
   ```

 - The program currently operates on two tables, `input` and `results`.
 - To run the program, make sure to pass in the unqiue identifier (currently email) of the subject that exists in the `input` table. They must have both the `self-words` and `concept-words` arrays filled with the words they selected from the test.
 - This will populate the `results` table with the appropriate scores and handle repeat tests on it's own.
 - In the case of a repeat (email already exists in `results`), the program will track `original_test_timestamp` (timestamp of first test result recorded), `latest_test_timestamp` (timestamp of most recent test result) and `tests_taken_count` (total number of tests taken)

---

## Notes

- Make sure MySQL is running before using the Python scripts.
- Update `sqlalchemy.create_engine` connection strings to match hosting db. All lines calling for `sqlalchemy.create_engine` are in `sql_insert.py` on line 8 and `sql_read,py` on line 5. 
- Results from test are stored in the `results` table in the testdb.